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
 */
final class DatevBookingAdapter {
    /**
     * Erzeugt die DATEV-V700-CSV als String.
     *
     * @param  list<array{amount: float, soll_haben: string, account: string, contra_account: string, tax_key: ?string, date: DateTimeImmutable, document_ref: string, text: string}>  $rows
     */
    public function generate(DatevBookingBatch $batch, DatevBookingConfig $config, array $rows): string {
        FinancialFormatsSupport::ensureAvailable();

        $fieldHeader = BookingBatchHeaderLine::createV700();
        $fieldCount = $fieldHeader->countFields();

        $builder = new BookingDocumentBuilder();
        $builder->setFieldHeader($fieldHeader);
        $builder->setClient($config->advisorNumber, $config->clientNumber);
        $builder->setDateRange(
            new DateTimeImmutable($batch->period_from->toDateString()),
            new DateTimeImmutable($batch->period_to->toDateString()),
        );
        $builder->setDescription($this->description($batch));

        foreach ($rows as $row) {
            $builder->addBooking($this->buildLine($fieldHeader, $fieldCount, $batch, $row));
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
     *
     * @param  array{amount: float, soll_haben: string, account: string, contra_account: string, tax_key: ?string, date: DateTimeImmutable, document_ref: string, text: string}  $row
     */
    private function buildLine(
        BookingBatchHeaderLine $header,
        int $fieldCount,
        DatevBookingBatch $batch,
        array $row,
    ): DataLine {
        $values = array_fill(0, $fieldCount, '');

        $set = static function (F $field, string $value) use (&$values, $header): void {
            $index = $header->getFieldIndex($field);
            if ($index >= 0) {
                $values[$index] = $value;
            }
        };

        $set(F::Umsatz, number_format(abs($row['amount']), 2, ',', ''));
        $set(F::SollHabenKennzeichen, $row['soll_haben']);
        $set(F::Konto, $row['account']);
        $set(F::Gegenkonto, $row['contra_account']);
        if ($row['tax_key'] !== null && $row['tax_key'] !== '') {
            $set(F::BUSchluessel, $row['tax_key']);
        }
        $set(F::Belegdatum, $row['date']->format('dm'));
        $set(F::Belegfeld1, $this->clip($row['document_ref'], 36));
        $set(F::Buchungstext, $this->clip($row['text'], 60));
        $set(F::Festschreibung, $batch->finalized_locked ? '1' : '0');

        return new DataLine($values, ';', '"');
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
