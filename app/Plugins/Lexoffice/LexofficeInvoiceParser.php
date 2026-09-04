<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use CommonToolkit\Enums\CurrencyCode;

/**
 * Zerlegt eine Lexoffice-Rechnung (`GET /invoices/{id}`) in Positionen und
 * Belegtexte — gemeinsam für den Belegspiegel (Feature 152, MVP-760) und den
 * positionsgenauen Leser aus Feature 151. Textpositionen fallen weg,
 * Bruttopreise werden über den Steuersatz auf netto gebracht, Rabatte
 * eingerechnet.
 *
 * @phpstan-type ParsedLine array{position: int, type: string, external_article_id: string, name: string, description: string, quantity: float, unit_name: string, unit_net: float, total_net: float, tax_rate: float|null}
 */
final class LexofficeInvoiceParser {
    /**
     * @param  array<string, mixed>  $invoice
     * @return array{currency: CurrencyCode, voucher_text: string, recipient: string, lines: list<ParsedLine>}
     */
    public static function parse(array $invoice): array {
        $currency = CurrencyCode::tryFrom((string) ($invoice['totalPrice']['currency'] ?? 'EUR')) ?? CurrencyCode::Euro;
        // Belegtexte: Bei Partnerrechnungen steht der Endkunde in Titel,
        // Einleitung oder Schlusstext — nicht in den Positionen.
        $voucherText = trim(implode(' ', array_filter([
            (string) ($invoice['title'] ?? ''),
            (string) ($invoice['introduction'] ?? ''),
            (string) ($invoice['remark'] ?? ''),
        ], static fn(string $part): bool => $part !== '')));
        $recipient = trim((string) ($invoice['address']['name'] ?? ''));

        $lines = [];
        foreach (array_values((array) ($invoice['lineItems'] ?? [])) as $position => $item) {
            if (! is_array($item) || (string) ($item['type'] ?? '') === 'text') {
                continue;
            }
            $unit = is_array($item['unitPrice'] ?? null) ? $item['unitPrice'] : [];
            $rate = isset($unit['taxRatePercentage']) ? (float) $unit['taxRatePercentage'] : null;
            $net = isset($unit['netAmount']) ? (float) $unit['netAmount'] : null;
            if ($net === null && isset($unit['grossAmount'])) {
                $net = (float) $unit['grossAmount'] / (1 + ($rate ?? 0.0) / 100);
            }
            if ($net === null) {
                continue;
            }
            $discount = (float) ($item['discountPercentage'] ?? 0);
            if ($discount > 0) {
                $net *= 1 - $discount / 100;
            }
            $quantity = (float) ($item['quantity'] ?? 1);
            $lines[] = [
                'position' => $position + 1,
                'type' => (string) ($item['type'] ?? ''),
                'external_article_id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_name' => (string) ($item['unitName'] ?? ''),
                'unit_net' => round($net, 4),
                'total_net' => round($net * $quantity, 2),
                'tax_rate' => $rate,
            ];
        }

        return ['currency' => $currency, 'voucher_text' => $voucherText, 'recipient' => $recipient, 'lines' => $lines];
    }
}
