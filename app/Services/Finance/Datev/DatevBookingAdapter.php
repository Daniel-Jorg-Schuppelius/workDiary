<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev;

use App\Models\Finance\DatevBookingBatch;
use App\Services\Finance\FinancialFormatsSupport;
use CommonToolkit\Entities\CSV\DataLine;
use CommonToolkit\FinancialFormats\Builders\DATEV\V700\BookingDocumentBuilder;
use CommonToolkit\FinancialFormats\Entities\DATEV\Header\BookingBatchHeaderLine;
use CommonToolkit\FinancialFormats\Enums\DATEV\HeaderFields\V700\BookingBatchHeaderField as F;
use CommonToolkit\FinancialFormats\Generators\DATEV\DatevDocumentGenerator;
use CommonToolkit\FinancialFormats\Parsers\DatevDocumentParser;
use CommonToolkit\Helper\Data\NumberHelper;
use DateTimeImmutable;
use Throwable;

/**
 * Adapter um `php-financial-formats` (DATEV V700, Feature 045, „Technischer
 * Zuschnitt"): kapselt {@see BookingDocumentBuilder} und
 * {@see DatevDocumentGenerator}, damit Toolkit-Klassen nicht direkt in
 * Controller/Eloquent-Modelle gelangen. Alle Aufrufe sind über
 * {@see FinancialFormatsSupport} gegen das Fehlen des optionalen Pakets
 * abgesichert.
 *
 * Ein Buchungssatz je Quelle: Soll Debitorenkonto an Haben Erlöskonto (bzw.
 * umgekehrt bei Gutschrift), Bruttobetrag, BU-Schlüssel steuert die DATEV-
 * Steueraufteilung. Belegfeld 1 = Rechnungsnummer, Buchungstext = Kunde/Leistung
 * (gekürzt), Belegdatum = Belegdatum der Quelle. Das Festschreibekennzeichen
 * (GoBD) wird je Buchung gemäß Batch-Stand gesetzt.
 *
 * Optionale Kanzlei-Felder (Feature 135, MVP-700): KOST1/KOST2, Zugeordnete
 * Fälligkeit, Skonto + Skontotyp und Beleglink — leer bleibt leer, damit
 * bestehende Stapel byte-identisch bleiben. `document_link` ist im Vertrag
 * vorgesehen, wird aber von keinem Lieferanten befüllt: DATEV erwartet dort
 * die Belegbild-GUID eines DATEV-Belegs (Format `BEDI "<guid>"`), und ein
 * solcher GUID ist an Rechnung/Auslage nicht hinterlegt. App-URLs gehören
 * nicht in das Feld — DATEV würde sie als ungültigen Link verwerfen.
 *
 * @phpstan-type BookingLine array{
 *   amount: float|numeric-string, soll_haben: string, account: string, contra_account: string,
 *   tax_key: ?string, date: DateTimeImmutable, document_ref: string, text: string, is_reversal?: bool,
 *   cost_center1?: ?string, cost_center2?: ?string, document_link?: ?string,
 *   due_on?: ?DateTimeImmutable, discount_amount?: float|numeric-string|null, discount_type?: ?int
 * }
 */
final class DatevBookingAdapter {
    /** Skontotyp (EXTF-Feld 94): 1 = Einkauf (Kreditor), 2 = Verkauf (Debitor). */
    public const DISCOUNT_TYPE_PURCHASE = 1;

    public const DISCOUNT_TYPE_SALES = 2;

    /**
     * Erzeugt die DATEV-V700-CSV als String.
     *
     * @param  list<BookingLine>  $rows
     */
    public function generate(DatevBookingBatch $batch, DatevBookingConfig $config, array $rows): string {
        return $this->generateBookings(
            new DateTimeImmutable($batch->period_from->toDateString()),
            new DateTimeImmutable($batch->period_to->toDateString()),
            (bool) $batch->finalized_locked,
            $this->description($batch),
            $config,
            $rows,
        );
    }

    /**
     * Batch-unabhängige Stapel-Erzeugung (Vollscan 2026-08-23, C2): auch die
     * DATEV-Übergabe aus den lokalen Festbuchungen (MVP-677) erzeugt damit
     * einen importierbaren EXTF-V700-Stapel statt einer Haus-CSV.
     *
     * @param  list<BookingLine>  $rows
     */
    public function generateBookings(DateTimeImmutable $from, DateTimeImmutable $to, bool $locked, string $description, DatevBookingConfig $config, array $rows): string {
        FinancialFormatsSupport::ensureAvailable();

        $fieldHeader = BookingBatchHeaderLine::createV700();
        $fieldCount = $fieldHeader->countFields();

        $builder = new BookingDocumentBuilder();
        $builder->setFieldHeader($fieldHeader);
        $builder->setClient($config->advisorNumber, $config->clientNumber);
        $builder->setDateRange($from, $to);
        $builder->setDescription($this->clip($description, 30));

        foreach ($rows as $row) {
            $builder->addBooking($this->buildLine($fieldHeader, $fieldCount, $locked, $row));
        }

        $document = $builder->build();

        return (new DatevDocumentGenerator())->generate($document, ';', '"', null, $config->encoding);
    }

    /**
     * Write→Read-Validierung (Feature 045, Akzeptanzkriterium „DATEV-Dateien
     * werden über `php-financial-formats` erzeugt UND erneut eingelesen/
     * validiert"): die soeben erzeugte CSV wird mit demselben Toolkit über einen
     * unabhängigen Codepfad (Parser statt Builder) wieder eingelesen. Geprüft
     * werden Formaterkennung (Kategorie Buchungsstapel), Formatversion (V700) und
     * die Anzahl der Buchungszeilen gegen die Erwartung.
     *
     * @return array{ok: bool, format_type: ?string, version: int, rows: int, expected_rows: int, errors: list<string>}
     */
    public function validateRoundtrip(string $csv, DatevBookingConfig $config, int $expectedRows): array {
        FinancialFormatsSupport::ensureAvailable();

        $errors = [];

        $analysis = DatevDocumentParser::analyzeFormat($csv);
        $formatTypeRaw = $analysis['format_type'] ?? null;
        $formatType = is_string($formatTypeRaw) ? $formatTypeRaw : null;
        $version = (int) ($analysis['version'] ?? 0);
        $supported = (bool) ($analysis['supported'] ?? false);

        if (! $supported) {
            $errors[] = (string) __('finance.datev.roundtrip.unsupported');
        }
        if ($version !== 700) {
            $errors[] = (string) __('finance.datev.roundtrip.version_mismatch', ['version' => $version]);
        }

        $rows = 0;
        try {
            $document = DatevDocumentParser::fromString($csv, ';', '"', true, $config->encoding);
            // Datenzeilen zählen: jede Buchung trägt ein Konto. getField()/hasField()
            // greifen auf die geparsten Zeilen zu, ohne den (in dieser Toolkit-
            // Version nicht verfügbaren) toAssoc()-Pfad zu benötigen.
            while ($document->hasField($rows, F::Konto)) {
                $rows++;
                if ($rows > 1_000_000) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $errors[] = (string) __('finance.datev.roundtrip.parse_failed', ['message' => $e->getMessage()]);
        }

        if ($rows !== $expectedRows) {
            $errors[] = (string) __('finance.datev.roundtrip.row_count_mismatch', [
                'actual' => $rows,
                'expected' => $expectedRows,
            ]);
        }

        return [
            'ok' => $errors === [],
            'format_type' => $formatType,
            'version' => $version,
            'rows' => $rows,
            'expected_rows' => $expectedRows,
            'errors' => $errors,
        ];
    }

    /** Menschenlesbare Formatkennung samt Version (UI-Anzeige, Kriterium „Formatversion sichtbar"). */
    public function formatLabel(): string {
        return 'DATEV-Buchungsstapel (EXTF V700)';
    }

    /**
     * Baut eine DATEV-Buchungszeile (DataLine) mit allen relevanten Feldern —
     * inkl. BU-Schlüssel und Festschreibung, die die Builder-Convenience
     * {@see BookingDocumentBuilder::addSimpleBooking()} nicht setzt.
     * MVP-334: Storno-Übergaben tragen das Generalumkehr-Kennzeichen
     * (EXTF-Feld 118 „Generalumkehr (GU)" = 1) — DATEV kehrt die Buchung um.
     *
     * @param  BookingLine  $row
     */
    private function buildLine(
        BookingBatchHeaderLine $header,
        int $fieldCount,
        bool $locked,
        array $row,
    ): DataLine {
        $values = array_fill(0, $fieldCount, '');

        $set = static function (F $field, string $value) use (&$values, $header): void {
            $index = $header->getFieldIndex($field);
            if ($index >= 0) {
                $values[$index] = $value;
            }
        };

        // Betrag als Decimal-String (Journal-Pfad, C1) oder Float (Alt-Pfad) —
        // beide enden im DATEV-Komma-Format ohne Tausendertrenner.
        $set(F::Umsatz, is_string($row['amount'])
            ? NumberHelper::toGermanFormat(NumberHelper::absPrecise($row['amount']), 2)
            : number_format(abs($row['amount']), 2, ',', ''));
        $set(F::SollHabenKennzeichen, $row['soll_haben']);
        $set(F::Konto, $row['account']);
        $set(F::Gegenkonto, $row['contra_account']);
        if ($row['tax_key'] !== null && $row['tax_key'] !== '') {
            $set(F::BUSchluessel, $row['tax_key']);
        }
        $set(F::Belegdatum, $row['date']->format('dm'));
        $set(F::Belegfeld1, $this->clip($row['document_ref'], 36));
        $set(F::Buchungstext, $this->clip($row['text'], 60));
        $set(F::Festschreibung, $locked ? '1' : '0');
        if (($row['is_reversal'] ?? false) === true) {
            $set(F::Generalumkehr, '1');
        }

        // Kanzlei-Felder (Feature 135): nur setzen, wenn ein Wert vorliegt.
        // Die DataLine schreibt ungequotet — Trennzeichen/Anführungszeichen
        // im Wert würden die Zeile zerreißen, deshalb werden sie entfernt.
        $costCenter1 = $this->plain($row['cost_center1'] ?? null, 8);
        if ($costCenter1 !== '') {
            $set(F::KOST1, $costCenter1);
        }
        $costCenter2 = $this->plain($row['cost_center2'] ?? null, 8);
        if ($costCenter2 !== '') {
            $set(F::KOST2, $costCenter2);
        }
        $documentLink = $this->plain($row['document_link'] ?? null, 210);
        if ($documentLink !== '') {
            $set(F::Beleglink, $documentLink);
        }
        if (($row['due_on'] ?? null) instanceof DateTimeImmutable) {
            $set(F::ZugeordneteFaelligkeit, $row['due_on']->format('dmY'));
        }
        $discount = $this->discountAmount($row['discount_amount'] ?? null);
        if ($discount !== null) {
            $set(F::Skonto, $discount);
            $set(F::SkontoTyp, (string) ($row['discount_type'] ?? self::DISCOUNT_TYPE_SALES));
        }

        return new DataLine($values, ';', '"');
    }

    /**
     * Skonto-Betrag im DATEV-Format (Feld 13, Muster `[1-9]\d{0,7},\d{2}`) —
     * null bei 0 oder ohne Kondition, damit das Feld leer bleibt.
     *
     * @param  float|numeric-string|null  $amount
     */
    private function discountAmount(float|string|null $amount): ?string {
        if ($amount === null) {
            return null;
        }
        $formatted = is_string($amount)
            ? NumberHelper::toGermanFormat(NumberHelper::absPrecise($amount), 2)
            : number_format(abs($amount), 2, ',', '');

        return preg_match('/^[1-9]\d{0,7},\d{2}$/', $formatted) === 1 ? $formatted : null;
    }

    /** Freitextfeld ohne CSV-Sonderzeichen, auf die DATEV-Feldlänge gekürzt. */
    private function plain(?string $value, int $max): string {
        $clean = str_replace([';', '"', "\r", "\n"], ' ', (string) $value);

        return $this->clip(preg_replace('/\s+/', ' ', $clean) ?? '', $max);
    }

    /**
     * Bezeichnung des Stapels (Meta-Header „Bezeichnung", max. 30 Zeichen).
     */
    private function description(DatevBookingBatch $batch): string {
        return $this->clip(sprintf('WorkDiary %s', $batch->period_from->format('Y-m')), 30);
    }

    private function clip(string $value, int $max): string {
        $value = trim($value);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
