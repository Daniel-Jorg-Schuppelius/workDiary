<?php
/*
 * Created on   : Tue Sep 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericSubscriptionReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\{BillingFrequency, SubscriptionProvider};
use App\Services\Reselling\Marketplace\Concerns\{OpensXlsxDocuments, ParsesImportValues};
use CommonToolkit\Contracts\Interfaces\CSV\FieldInterface;
use CommonToolkit\Entities\CSV\HeaderLine;
use CommonToolkit\Entities\XLSX\Cell;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{CryptoHelper, StringHelper};
use CommonToolkit\Parsers\CSVDocumentParser;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;

/**
 * Generische Abo-Liste (Feature 152): CSV oder XLSX mit frei benannten
 * Spalten — deutsche und englische Überschriften werden erkannt. Pflicht
 * sind Firma, Produkt und Beginn; Kennung, Menge, Ende, Rhythmus, Laufzeit,
 * Einkaufs-/Verkaufspreis, Bestellnummer und Anbieter sind optional. Ohne
 * Kennung entsteht sie aus Firma, Produkt und Beginn — stabil, solange die
 * Zeile gleich bleibt. Der Anbieter kommt aus der Spalte oder dem Dialog.
 * Zeilen mit unlesbaren Werten werden als Befund gemeldet und übersprungen,
 * nie still ergänzt (Review 2026-09-10, B15/B16/C3/C4).
 */
final class GenericSubscriptionReader {
    use OpensXlsxDocuments;
    use ParsesImportValues;

    /** @var array<string, list<string>> Zielspalte → erkannte Überschriften (normalisiert) */
    private const COLUMNS = [
        'id' => ['kennung', 'id', 'vertrag', 'vertragsnummer', 'vertragsnr', 'contract', 'contract id', 'entitlement', 'subscription', 'subscription id', 'abo', 'abo-id', 'abo id'],
        'company' => ['firma', 'kunde', 'unternehmen', 'company', 'customer', 'endkunde', 'halter'],
        'product' => ['produkt', 'product', 'edition', 'artikel', 'lizenz', 'license', 'licence', 'leistung', 'bezeichnung'],
        'quantity' => ['menge', 'anzahl', 'lizenzen', 'quantity', 'qty', 'seats', 'stück', 'stueck', 'nutzer', 'users'],
        'start' => ['beginn', 'start', 'von', 'ab', 'startdatum', 'starts on', 'starts_on', 'start date', 'vertragsbeginn', 'laufzeitbeginn'],
        'end' => ['ende', 'bis', 'enddatum', 'ends on', 'ends_on', 'end date', 'vertragsende', 'laufzeitende', 'kündigung', 'kuendigung'],
        'frequency' => ['intervall', 'rhythmus', 'abrechnung', 'frequency', 'interval', 'billing', 'zahlweise', 'turnus'],
        'term' => ['laufzeit', 'laufzeit (monate)', 'laufzeit monate', 'term', 'term months', 'term_months', 'mindestlaufzeit'],
        'purchase' => ['einkauf', 'einkaufspreis', 'ek', 'ek-preis', 'purchase', 'purchase price', 'cost', 'kosten', 'einkauf je stück', 'einkauf/stück'],
        'sale' => ['verkauf', 'verkaufspreis', 'vk', 'vk-preis', 'sale', 'sale price', 'price', 'preis', 'verkauf je stück', 'verkauf/stück'],
        'fee' => ['gebühr', 'gebuehr', 'gesamt', 'gesamtpreis', 'total', 'total fee', 'summe', 'betrag'],
        'order' => ['bestellung', 'bestellnummer', 'bestellnr', 'order', 'order id', 'auftrag', 'auftragsnummer'],
        'provider' => ['anbieter', 'provider', 'lieferant', 'vendor', 'quelle', 'source'],
        'status' => ['status', 'zustand'],
        'currency' => ['währung', 'waehrung', 'currency'],
    ];

    private const REQUIRED = ['company', 'product', 'start'];

    /** Stückpreise mit vier, Summen mit zwei Nachkommastellen (B19). */
    private const UNIT_SCALE = 4;

    private const TOTAL_SCALE = 2;

    /** „Einkaufspreis (EUR)" / „Preis (netto)" → „einkaufspreis" / „preis" — das Toolkit kennt keinen Einheiten-Zusatz. */
    private const UNIT_SUFFIX = '/\s*\((?:eur|€|netto|net)\)$/u';

    /**
     * @param  int  $maxRows  Datenzeilen je XLSX-Blatt; darüber bricht der Import ab (kein stilles Kürzen)
     */
    public function __construct(private readonly int $maxRows = self::XLSX_MAX_ROWS) {}

    public function read(string $file, SubscriptionProvider $provider = SubscriptionProvider::Other): PurchasesImport {
        $name = basename($file);
        if (! is_readable($file)) {
            throw new RuntimeException((string) __('resale_import.file.unreadable', ['file' => $name]));
        }
        $skipped = [];
        [$headers, $rows] = str_ends_with(mb_strtolower($file), '.xlsx') || str_ends_with(mb_strtolower($file), '.xlsm')
            ? $this->readXlsx($file)
            : $this->readCsv($file, $skipped);

        $index = $this->columnIndex($headers);
        $missing = [];
        foreach (self::REQUIRED as $column) {
            if (! isset($index[$column])) {
                $missing[] = (string) __('resale_import.column.' . $column);
            }
        }
        if ($missing !== []) {
            throw new RuntimeException((string) __('resale_import.file.missing_columns', ['columns' => implode(', ', $missing)]));
        }

        $entitlements = [];
        $issues = [];
        /** @var array<string, int> $seenIds Kennung (groß) → erste Zeile */
        $seenIds = [];
        foreach ($rows as $line => $row) {
            if ($row === null) {
                $issues[] = $skipped[$line] ?? '';

                continue;
            }
            $cell = static function (string $column) use ($row, $index): ?Cell {
                $position = $index[$column] ?? null;

                return $position === null ? null : ($row[$position] ?? null);
            };
            $text = static fn(string $column): string => trim($cell($column)?->toCanonicalString() ?? '');
            $companyName = $text('company');
            $product = $text('product');
            if ($companyName === '' && $product === '' && $text('start') === '') {
                continue; // Leerzeile
            }
            $issue = static fn(string $key, array $params = []): string => (string) __('resale_import.row.' . $key, $params + ['line' => $line, 'company' => $companyName !== '' ? $companyName : $product]);
            if ($companyName === '' || $product === '') {
                $issues[] = $issue('missing_company_or_product');

                continue;
            }
            $startsOn = self::importDate($cell('start')?->getValue());
            if ($startsOn === null) {
                $issues[] = $issue('start_unreadable', ['value' => $text('start')]);

                continue;
            }
            $endRaw = $text('end');
            $endsOn = $endRaw === '' ? null : self::importDate($cell('end')?->getValue());
            if ($endRaw !== '' && $endsOn === null) {
                $issues[] = $issue('end_unreadable', ['value' => $endRaw]);

                continue;
            }
            if ($endsOn !== null && $endsOn->lessThanOrEqualTo($startsOn)) {
                $issues[] = $issue('end_before_start');

                continue;
            }
            $frequencyRaw = $text('frequency');
            $frequency = $frequencyRaw === '' ? BillingFrequency::Yearly : BillingFrequency::fromLabel($frequencyRaw);
            if ($frequency === null) {
                $issues[] = $issue('unknown_frequency', ['value' => $frequencyRaw]);

                continue;
            }
            // Menge: leer = 1; „4 Stück", „0", „2,5" oder „8 S" (= −8) sind Befunde, keine 1.
            $quantityRaw = $text('quantity');
            $quantity = $quantityRaw === '' ? 1 : self::importInteger($cell('quantity')?->getValue());
            if ($quantity === null || $quantity <= 0) {
                $issues[] = $issue('quantity_invalid', ['value' => $quantityRaw]);

                continue;
            }
            $currencyRaw = $text('currency');
            $currency = $currencyRaw === '' ? CurrencyCode::Euro : CurrencyCode::tryFrom(strtoupper($currencyRaw));
            if ($currency === null) {
                $issues[] = $issue('unknown_currency', ['value' => $currencyRaw]);

                continue;
            }
            $unitFee = self::importMoney($cell('purchase')?->getValue(), $currency, self::UNIT_SCALE);
            $fee = self::importMoney($cell('fee')?->getValue(), $currency, self::TOTAL_SCALE);
            if ($unitFee === null && $fee !== null) {
                $unitFee = $fee->withScale(self::UNIT_SCALE)->dividedBy($quantity); // erst Scale, dann teilen: 100/3 → 33,3333
            }
            $unitFee ??= Money::zero($currency, self::UNIT_SCALE);
            $rowProvider = $this->provider($text('provider')) ?? $provider;
            $externalId = $text('id');
            if ($externalId === '') {
                // Stabil, solange Firma, Produkt und Beginn gleich bleiben.
                $externalId = 'gen:' . substr((string) CryptoHelper::hash(MarketplaceCompany::matchKey($companyName) . '|' . MarketplaceCompany::matchKey($product) . '|' . $startsOn->toDateString()), 0, 24);
            }
            $idKey = mb_strtoupper($externalId);
            if (isset($seenIds[$idKey])) {
                $issues[] = $issue('duplicate_id', ['value' => $externalId, 'other' => $seenIds[$idKey]]);

                continue;
            }
            $seenIds[$idKey] = $line;
            $termRaw = $text('term');
            $term = $termRaw === '' ? null : self::importInteger($cell('term')?->getValue());
            if ($termRaw !== '' && ($term === null || $term <= 0)) {
                $issues[] = $issue('term_unreadable', ['value' => $termRaw]);
                $term = null;
            }

            $entitlements[] = new MarketplaceEntitlement(
                company: new MarketplaceCompany(key: MarketplaceCompany::matchKey($companyName), name: $companyName, email: null, phone: null),
                entitlementId: $externalId,
                orderId: $text('order'),
                application: $product,
                edition: $product,
                fee: $fee ?? $unitFee->times($quantity)->withScale(self::TOTAL_SCALE),
                frequency: $frequency,
                startsOn: $startsOn,
                endsOn: $endsOn,
                status: $text('status'),
                assignedUsers: 0,
                sourceLine: $line,
                source: MarketplaceEntitlement::SOURCE_GENERIC,
                quantity: $quantity,
                unitFee: $unitFee,
                termMonths: $term,
                provider: $rowProvider->value,
                salePrice: self::importMoney($cell('sale')?->getValue(), $currency, self::UNIT_SCALE),
            );
        }

        return new PurchasesImport($entitlements, $issues);
    }

    /**
     * Zeilenweise über `streamAll`, damit eine Zeile mit abweichender
     * Feldzahl (Excel lässt leere Endspalten weg, unmaskiertes Trennzeichen)
     * nicht die ganze Datei kippt: zu wenige Felder gelten als leer, zu
     * viele als Befund — nur wenn sie Inhalt tragen. `fromFile(strict: false)`
     * kürzt überzählige Felder still, dann wäre das nicht mehr unterscheidbar.
     *
     * @param  array<int, string>  $skipped  Zeilennummer → Befund (Zeile fehlt dann als null in den Zeilen)
     * @return array{0: list<string>, 1: array<int, array<int, Cell>|null>}  Kopfzeile, Zeilennummer → Zellen
     */
    private function readCsv(string $file, array &$skipped): array {
        $delimiter = CSVDocumentParser::detectDelimiter($file);
        $headers = [];
        $rows = [];
        foreach (CSVDocumentParser::streamAll($file, $delimiter, '"', true) as $line => $parsed) {
            if ($parsed instanceof HeaderLine) {
                $headers = array_map('strval', array_values($parsed->getColumnNames()));

                continue;
            }
            $values = array_map(static fn(FieldInterface $field): string => $field->getValue(), array_values($parsed->getFields()));
            if (count($values) > count($headers) && trim(implode('', array_slice($values, count($headers)))) !== '') {
                $skipped[(int) $line] = (string) __('resale_import.row.too_many_fields', ['line' => $line, 'expected' => count($headers), 'found' => count($values)]);
                $rows[(int) $line] = null;

                continue;
            }
            $cells = [];
            foreach (array_keys($headers) as $position) {
                $cells[$position] = new Cell($values[$position] ?? '', 's');
            }
            $rows[(int) $line] = $cells;
        }
        if ($headers === []) {
            throw new RuntimeException((string) __('resale_import.file.no_header', ['file' => basename($file)]));
        }

        return [$headers, $rows];
    }

    /**
     * @return array{0: list<string>, 1: array<int, array<int, Cell>|null>}  Kopfzeile, Zeilennummer → Zellen
     */
    private function readXlsx(string $file): array {
        $sheet = self::openXlsx($file, $this->maxRows, 'resale_import.file.xlsx_unreadable')->getFirstSheet();
        if ($sheet === null) {
            throw new RuntimeException((string) __('resale_import.file.no_sheet', ['file' => basename($file)]));
        }
        $headers = array_values($sheet->getHeaderNames());
        $rows = [];
        foreach ($sheet->getRows() as $row) {
            $rows[$row->getRowIndex()] = array_values($row->getCells());
        }

        return [$headers, $rows];
    }

    /**
     * Frei benannte Überschriften → Zielspalten. Eigene Alias-Suche statt
     * `getColumnIndexByAliases()`, weil die Kopfzeile aus CSV und XLSX gleich
     * behandelt wird und der Einheiten-Zusatz vor dem Vergleich fallen muss.
     *
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function columnIndex(array $headers): array {
        $index = [];
        foreach ($headers as $position => $name) {
            $normalized = StringHelper::normalizeColumnName($name);
            $normalized = trim(preg_replace(self::UNIT_SUFFIX, '', $normalized) ?? $normalized);
            foreach (self::COLUMNS as $column => $aliases) {
                if (! isset($index[$column]) && in_array($normalized, $aliases, true)) {
                    $index[$column] = $position;
                    break;
                }
            }
        }

        return $index;
    }

    private function provider(string $raw): ?SubscriptionProvider {
        $normalized = mb_strtolower(trim($raw));
        if ($normalized === '') {
            return null;
        }
        foreach (SubscriptionProvider::cases() as $case) {
            if ($normalized === $case->value || $normalized === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return match (true) {
            str_contains($normalized, 'telekom') => SubscriptionProvider::TelekomMarketplace,
            str_contains($normalized, 'quality') => SubscriptionProvider::QualityHosting,
            str_contains($normalized, 'domain') => SubscriptionProvider::DomainReselling,
            str_contains($normalized, 'manuell') || str_contains($normalized, 'manual') => SubscriptionProvider::Manual,
            default => SubscriptionProvider::Other,
        };
    }
}
