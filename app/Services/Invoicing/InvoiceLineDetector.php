<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceLineDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Regelbasierte Positionserkennung für unstrukturierte Rechnungsdateien
 * (Feature 088, Regel-Stufe vor der KI): eine Kopfzeile mit erkennbaren
 * Spaltenlabels definiert das Raster, Datenzeilen darunter werden zu
 * Positions-Vorschlägen. Konservativ: ohne erkennbare Kopfzeile oder bei
 * widersprüchlichen Beträgen liefert der Detektor nichts — die Sammelzeile
 * bleibt dann der sichere Fallback.
 */
class InvoiceLineDetector {
    /** @var array<string, list<string>> Spaltenlabels (deutsch/englisch, klein). */
    private const COLUMN_LABELS = [
        'position' => ['pos', 'pos.', 'position', 'nr', 'nr.', '#'],
        'description' => ['bezeichnung', 'beschreibung', 'artikel', 'leistung', 'text', 'description', 'item', 'produkt', 'tätigkeit'],
        'quantity' => ['menge', 'anzahl', 'qty', 'quantity', 'stunden', 'std'],
        'unit' => ['einheit', 'me', 'unit', 'einh', 'einh.'],
        'unit_price' => ['einzelpreis', 'e-preis', 'ep', 'preis', 'preis/einheit', 'unit price', 'price', 'satz', 'stundensatz'],
        'amount' => ['betrag', 'gesamt', 'gesamtpreis', 'gesamtbetrag', 'summe', 'gp', 'total', 'amount', 'netto'],
        'tax_rate' => ['ust', 'ust.', 'mwst', 'mwst.', 'steuer', 'steuersatz', 'vat', 'tax', 'ust %', 'mwst %'],
    ];

    /** Zeilen, deren erste Textzelle so beginnt, beenden den Positionsblock. */
    private const SUMMARY_PREFIXES = [
        'summe', 'zwischensumme', 'gesamt', 'netto', 'brutto', 'umsatzsteuer',
        'mehrwertsteuer', 'mwst', 'ust', 'übertrag', 'total', 'subtotal', 'vat',
        'zahlbetrag', 'rechnungsbetrag', 'skonto',
    ];

    /**
     * @param  list<list<mixed>>  $rows  Tabellenzeilen (Zellwerte roh: string|int|float)
     * @return list<array{position: int, description: string, quantity: string, unit: string, unit_price: string, tax_rate: ?string, discount_amount: null, amount: string}>|null
     */
    public function detectFromRows(array $rows): ?array {
        $header = $this->findHeader($rows);
        if ($header === null) {
            return null;
        }
        [$headerIndex, $columns] = $header;

        $lines = [];
        $position = 0;
        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $rows[$index];
            $description = trim($this->stringAt($row, $columns['description']));
            if ($description !== '' && $this->isSummaryRow($description)) {
                break;
            }

            $quantity = $this->numberAt($row, $columns['quantity'] ?? null);
            $unitPrice = $this->numberAt($row, $columns['unit_price'] ?? null);
            $amount = $this->numberAt($row, $columns['amount'] ?? null);
            if ($description === '' || ($amount === null && ($quantity === null || $unitPrice === null))) {
                // Leer-/Layoutzeile: überspringen, aber Block nicht abbrechen —
                // erst eine Summenzeile beendet die Tabelle.
                continue;
            }

            $quantity ??= 1.0;
            if ($quantity <= 0.0) {
                continue;
            }
            // Nach dem Guard oben gilt: fehlt der Betrag, ist der Preis da —
            // und umgekehrt; beide Ableitungen sind daher vollständig.
            $unitPrice ??= (float) $amount / $quantity;
            $amount ??= $quantity * $unitPrice;
            if ($unitPrice < 0.0 || $amount < 0.0) {
                continue;
            }
            // Zeilen-Gegenprobe: Menge × Preis muss den Betrag treffen (1 ct
            // Toleranz je Zeile) — sonst ist das Raster nicht vertrauenswürdig.
            if (abs($quantity * $unitPrice - $amount) > 0.011) {
                return null;
            }

            $taxRate = $this->numberAt($row, $columns['tax_rate'] ?? null);
            $unit = trim($this->stringAt($row, $columns['unit'] ?? null));

            $position++;
            $lines[] = [
                'position' => $position,
                'description' => $description,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit' => $unit !== '' ? mb_substr($unit, 0, 20) : 'Stk.',
                'unit_price' => number_format($unitPrice, 4, '.', ''),
                'tax_rate' => $taxRate !== null && $taxRate <= 100.0 ? number_format($taxRate, 2, '.', '') : null,
                'discount_amount' => null,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $lines !== [] ? $lines : null;
    }

    /**
     * Spaltentreuer Text (PDF `rowAlignedText`): Zeilen an 2+ Leerzeichen in
     * Zellen teilen und wie eine Tabelle behandeln.
     *
     * @return list<array{position: int, description: string, quantity: string, unit: string, unit_price: string, tax_rate: ?string, discount_amount: null, amount: string}>|null
     */
    public function detectFromAlignedText(string $text): ?array {
        $rows = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $cells = preg_split('/\s{2,}/u', trim($line)) ?: [];
            $cells = array_values(array_filter($cells, static fn(string $cell): bool => $cell !== ''));
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $this->detectFromRows($rows);
    }

    /**
     * Kopfzeile suchen: mindestens Beschreibung + (Betrag ODER Menge+Preis).
     *
     * @param  list<list<mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}|null
     */
    private function findHeader(array $rows): ?array {
        foreach ($rows as $index => $row) {
            $columns = [];
            foreach ($row as $cellIndex => $cell) {
                $label = mb_strtolower(trim($this->cellToString($cell)));
                $label = rtrim($label, ':');
                if ($label === '') {
                    continue;
                }
                foreach (self::COLUMN_LABELS as $field => $candidates) {
                    if (! isset($columns[$field]) && in_array($label, $candidates, true)) {
                        $columns[$field] = $cellIndex;
                        break;
                    }
                }
            }

            $hasCore = isset($columns['description'])
                && (isset($columns['amount']) || (isset($columns['quantity']) && isset($columns['unit_price'])));
            if ($hasCore) {
                return [$index, $columns];
            }
        }

        return null;
    }

    private function isSummaryRow(string $firstText): bool {
        $normalized = mb_strtolower($firstText);
        foreach (self::SUMMARY_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<mixed> $row */
    private function stringAt(array $row, ?int $index): string {
        if ($index === null || ! array_key_exists($index, $row)) {
            return '';
        }

        return $this->cellToString($row[$index]);
    }

    /** @param list<mixed> $row */
    private function numberAt(array $row, ?int $index): ?float {
        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }
        $value = $row[$index];
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $raw = trim($this->cellToString($value));
        if ($raw === '') {
            return null;
        }
        // Währungssymbole/Einheiten neben der Zahl tolerieren („119,00 EUR", „19 %").
        $raw = trim((string) preg_replace('/(?:EUR|USD|CHF|GBP|€|\$|£|%)/u', '', $raw));
        $normalized = NumberHelper::normalizeDecimalStringOrNull($raw, CountryCode::Germany);

        return $normalized !== null ? (float) $normalized : null;
    }

    private function cellToString(mixed $cell): string {
        return match (true) {
            $cell instanceof \DateTimeInterface => $cell->format('d.m.Y'),
            is_scalar($cell) => (string) $cell,
            default => '',
        };
    }
}
