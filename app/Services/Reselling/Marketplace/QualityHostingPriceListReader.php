<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualityHostingPriceListReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use App\Services\Reselling\Marketplace\Concerns\{NormalizesHeaders, ParsesImportValues};
use Carbon\CarbonImmutable;
use CommonToolkit\Entities\XLSX\{Cell, Sheet};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Parsers\XLSXDocumentParser;
use RuntimeException;
use Throwable;

/**
 * Liest die Reseller-Preisliste des Quality-Hosting-Partnerportals (XLSX):
 * Blatt „Deckblatt" mit Gültigkeit, Blatt „Preisdaten" mit einer Zeile je
 * Produkttarif × Laufzeit × Zahlungsintervall (Einkaufspreis und
 * Hersteller-UVP je Monat und je Intervall, netto). Gültigkeit je Zeile aus
 * „Gültig ab", sonst vom Deckblatt; die Liste trägt das früheste Datum. Ohne
 * beides bleibt `validFrom` null — der Importer nimmt dann das Importdatum
 * (Review 2026-09-10, A12).
 */
final class QualityHostingPriceListReader {
    use NormalizesHeaders;
    use ParsesImportValues;

    private const REQUIRED = ['produkttarif', 'vertragslaufzeit in monaten', 'zahlungsintervall', 'preis pro zahlungsintervall'];

    /** Stückpreise (je Monat/Intervall) mit vier Nachkommastellen (B19). */
    private const UNIT_SCALE = 4;

    public function read(string $file): PriceList {
        $name = basename($file);
        if (! is_readable($file)) {
            throw new RuntimeException((string) __('resale_import.pricelist.unreadable', ['file' => $name]));
        }

        try {
            $document = XLSXDocumentParser::fromFile($file, true);
        } catch (Throwable $e) {
            throw new RuntimeException((string) __('resale_import.pricelist.unreadable_reason', ['file' => $name, 'reason' => str_replace($file, $name, $e->getMessage())]), 0, $e);
        }

        $sheet = null;
        foreach ($document->getSheets() as $candidate) {
            if (isset(self::headerIndex(self::sheetHeaderNames($candidate))['produkttarif'])) {
                $sheet = $candidate;
                break;
            }
        }
        if ($sheet === null) {
            throw new RuntimeException((string) __('resale_import.pricelist.no_sheet'));
        }

        $index = self::headerIndex(self::sheetHeaderNames($sheet));
        $missing = array_values(array_diff(self::REQUIRED, array_keys($index)));
        if ($missing !== []) {
            throw new RuntimeException((string) __('resale_import.pricelist.missing_columns', ['columns' => implode(', ', $missing)]));
        }

        $coverValidFrom = $this->coverValidFrom(array_values($document->getSheets()));
        $listValidFrom = null;
        $entries = [];
        $issues = [];
        foreach ($sheet->getRows() as $row) {
            $line = $row->getRowIndex();
            $cells = array_values($row->getCells());
            $cell = static function (string $column) use ($cells, $index): ?Cell {
                $position = $index[$column] ?? null;

                return $position === null ? null : ($cells[$position] ?? null);
            };
            $text = static fn(string $column): string => trim($cell($column)?->toCanonicalString() ?? '');

            $product = $text('produkttarif');
            if ($product === '') {
                continue;
            }
            $interval = BillingFrequency::fromLabel($text('zahlungsintervall'));
            $term = self::importInteger($cell('vertragslaufzeit in monaten')?->getValue());
            $price = self::importMoney($cell('preis pro zahlungsintervall')?->getValue(), CurrencyCode::Euro, self::UNIT_SCALE);
            $monthly = self::importMoney($cell('preis pro monat')?->getValue(), CurrencyCode::Euro, self::UNIT_SCALE);
            if ($interval === null || $term === null || $term <= 0 || $price === null) {
                $issues[] = (string) __('resale_import.pricelist.row_invalid', ['line' => $line, 'product' => $product]);

                continue;
            }
            $rowValidFrom = self::importDate($cell('gültig ab')?->getValue());
            if ($rowValidFrom === null && $text('gültig ab') !== '') {
                $issues[] = (string) __('resale_import.pricelist.valid_from_unreadable', ['line' => $line, 'product' => $product, 'value' => $text('gültig ab')]);
            }
            $validFrom = $rowValidFrom ?? $coverValidFrom;
            if ($validFrom !== null && ($listValidFrom === null || $validFrom->lessThan($listValidFrom))) {
                $listValidFrom = $validFrom;
            }

            $entries[] = new PriceListEntry(
                product: $product,
                termMonths: $term,
                interval: $interval,
                pricePerMonth: $monthly ?? $price->dividedBy($term),
                uvpPerMonth: self::importMoney($cell('hersteller-uvp pro monat')?->getValue(), CurrencyCode::Euro, self::UNIT_SCALE),
                pricePerInterval: $price,
                uvpPerInterval: self::importMoney($cell('hersteller-uvp pro zahlungsintervall')?->getValue(), CurrencyCode::Euro, self::UNIT_SCALE),
                offerKey: $text('offer-key'),
                sourceLine: $line,
                validFrom: $validFrom,
            );
        }
        if ($entries !== [] && $listValidFrom === null) {
            $issues[] = (string) __('resale_import.pricelist.no_valid_from');
        }

        return new PriceList($entries, $listValidFrom, $issues);
    }

    /**
     * Deckblatt: die Zelle rechts neben „Gültigkeit ab" (Datumszelle oder Text).
     *
     * @param  list<Sheet>  $sheets
     */
    private function coverValidFrom(array $sheets): ?CarbonImmutable {
        foreach ($sheets as $sheet) {
            $rows = $sheet->getRows();
            $header = $sheet->getHeader();
            if ($header !== null) {
                array_unshift($rows, $header);
            }
            foreach ($rows as $row) {
                $cells = array_values($row->getCells());
                foreach ($cells as $position => $cell) {
                    if (mb_strtolower(trim($cell->toCanonicalString())) !== 'gültigkeit ab') {
                        continue;
                    }
                    $date = self::importDate(($cells[$position + 1] ?? null)?->getValue());
                    if ($date !== null) {
                        return $date;
                    }
                }
            }
        }

        return null;
    }
}
