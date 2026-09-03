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
use Carbon\CarbonImmutable;
use CommonToolkit\Entities\XLSX\{Cell, Sheet};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\Parsers\XLSXDocumentParser;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;
use Throwable;

/**
 * Liest die Reseller-Preisliste des Quality-Hosting-Partnerportals (XLSX):
 * Blatt „Deckblatt" mit Gültigkeit, Blatt „Preisdaten" mit einer Zeile je
 * Produkttarif × Laufzeit × Zahlungsintervall (Einkaufspreis und
 * Hersteller-UVP je Monat und je Intervall, netto).
 */
final class QualityHostingPriceListReader {
    private const REQUIRED = ['produkttarif', 'vertragslaufzeit in monaten', 'zahlungsintervall', 'preis pro zahlungsintervall'];

    public function read(string $file): PriceList {
        if (! is_readable($file)) {
            throw new RuntimeException("Preisliste nicht lesbar: {$file}");
        }

        try {
            $document = XLSXDocumentParser::fromFile($file, true);
        } catch (Throwable $e) {
            throw new RuntimeException("Preisliste nicht lesbar: {$file} ({$e->getMessage()})", 0, $e);
        }

        $sheet = null;
        foreach ($document->getSheets() as $candidate) {
            $names = array_map(static fn($name): string => self::normalizeHeader((string) $name), array_values($candidate->getHeaderNames()));
            if (in_array('produkttarif', $names, true)) {
                $sheet = $candidate;
                break;
            }
        }
        if ($sheet === null) {
            throw new RuntimeException('Preisliste ohne Blatt „Preisdaten" (Spalte „Produkttarif" fehlt).');
        }

        $index = [];
        foreach (array_values($sheet->getHeaderNames()) as $position => $name) {
            $index[self::normalizeHeader((string) $name)] = (int) $position;
        }
        $missing = array_values(array_diff(self::REQUIRED, array_keys($index)));
        if ($missing !== []) {
            throw new RuntimeException('Pflichtspalten der Preisliste fehlen: ' . implode(', ', $missing));
        }

        $entries = [];
        $issues = [];
        foreach (array_values($sheet->getRows()) as $offset => $row) {
            $line = $offset + 2;
            $cells = array_values($row->getCells());
            $cell = static function (string $column) use ($cells, $index): ?Cell {
                $position = $index[$column] ?? null;

                return $position === null ? null : ($cells[$position] ?? null);
            };
            $text = static fn(string $column): string => trim((string) ($cell($column)?->toCanonicalString() ?? ''));

            $product = $text('produkttarif');
            if ($product === '') {
                continue;
            }
            $interval = BillingFrequency::fromLabel($text('zahlungsintervall'));
            $term = (int) round((float) ($cell('vertragslaufzeit in monaten')?->getValue() ?? 0));
            $price = $this->money($cell('preis pro zahlungsintervall'));
            $monthly = $this->money($cell('preis pro monat'));
            if ($interval === null || $term <= 0 || $price === null) {
                $issues[] = sprintf('Preisliste Zeile %d (%s): Laufzeit, Intervall oder Preis nicht lesbar - übersprungen.', $line, $product);

                continue;
            }

            $entries[] = new PriceListEntry(
                product: $product,
                termMonths: $term,
                interval: $interval,
                pricePerMonth: $monthly ?? $price->dividedBy(max(1, $term)),
                uvpPerMonth: $this->money($cell('hersteller-uvp pro monat')),
                pricePerInterval: $price,
                uvpPerInterval: $this->money($cell('hersteller-uvp pro zahlungsintervall')),
                offerKey: $text('offer-key'),
                sourceLine: $line,
            );
        }

        return new PriceList($entries, $this->validFrom(array_values($document->getSheets())), $issues);
    }

    /**
     * @param  list<Sheet>  $sheets
     */
    private function validFrom(array $sheets): ?CarbonImmutable {
        foreach ($sheets as $sheet) {
            foreach ($sheet->toArray(true) as $row) {
                $values = array_values(array_map(static fn($value): string => trim((string) $value), $row));
                foreach ($values as $position => $value) {
                    if (mb_strtolower($value) !== 'gültigkeit ab') {
                        continue;
                    }
                    $raw = $values[$position + 1] ?? '';
                    foreach (['d.m.Y', 'Y-m-d'] as $format) {
                        try {
                            $parsed = CarbonImmutable::createFromFormat('!' . $format, $raw);
                        } catch (Throwable) {
                            continue;
                        }
                        if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $raw) {
                            return $parsed;
                        }
                    }
                }
            }
        }

        return null;
    }

    private static function normalizeHeader(string $name): string {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    private function money(?Cell $cell): ?Money {
        if ($cell === null || $cell->isEmpty()) {
            return null;
        }
        $value = $cell->getValue();
        if (is_int($value) || is_float($value)) {
            return Money::ofFloat(round((float) $value, 2), CurrencyCode::Euro);
        }
        $decimal = NumberHelper::normalizeDecimalStringOrNull((string) $cell->toCanonicalString());
        if ($decimal === null) {
            return null;
        }
        try {
            return Money::of($decimal, CurrencyCode::Euro);
        } catch (Throwable) {
            return null;
        }
    }
}
