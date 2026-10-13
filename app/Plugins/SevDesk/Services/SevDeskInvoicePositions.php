<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskInvoicePositions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Invoicing\{Invoice, InvoiceItem};

/**
 * Rechnungspositionen (MVP-865) als sevDesk-`InvoicePos` (Phase 145, MVP-1166):
 * Positionsrabatte stecken im Einzelpreis, ein Belegrabatt geht als negative
 * Position je Steuersatz hinüber — sevDesk kennt am Beleg nur ganze Prozent.
 * Titel und Text haben in sevDesk keine Entsprechung und entfallen.
 */
final class SevDeskInvoicePositions {
    /**
     * @param  array{default_vat_rate: float, unity_piece_id: int, unity_hour_id: int}  $config
     * @return list<array<string, mixed>>
     */
    public static function from(Invoice $invoice, array $config): array {
        $fallback = (float) ($invoice->tax_rate?->getNumericValue() ?? $config['default_vat_rate']);
        $hours = array_map('mb_strtolower', ['h', 'Std', 'Std.', 'Stunde', 'Stunden', (string) __('invoicing.unit_hour')]);
        $positions = [];
        foreach ($invoice->items as $item) {
            /** @var InvoiceItem $item */
            if (! $item->lineKind()->isPriced()) {
                continue;
            }
            $quantity = (float) $item->lineQuantity();
            if ($quantity === 0.0) {
                continue;
            }
            $unit = mb_strtolower(trim((string) $item->lineUnit()));
            $positions[] = array_filter([
                'objectName' => 'InvoicePos',
                'mapAll' => true,
                'name' => mb_substr(trim((string) $item->description) ?: (string) __('Position'), 0, 255),
                'text' => mb_strlen((string) $item->description) > 255 ? (string) $item->description : null,
                'quantity' => round($quantity, 4),
                'price' => round($item->netAmount()->toFloat() / $quantity, 4),
                'taxRate' => (float) ($item->taxRate()?->getNumericValue() ?? $fallback),
                'unity' => ['id' => in_array($unit, $hours, true) ? $config['unity_hour_id'] : $config['unity_piece_id'], 'objectName' => 'Unity'],
            ], static fn ($value): bool => $value !== null);
        }

        foreach ($invoice->documentTotals()['by_rate'] as $group) {
            if ($group['allowance']->isZero()) {
                continue;
            }
            $positions[] = [
                'objectName' => 'InvoicePos',
                'mapAll' => true,
                'name' => (string) __('Rabatt'),
                'quantity' => 1,
                'price' => -$group['allowance']->toFloat(),
                'taxRate' => $group['rate'],
                'unity' => ['id' => $config['unity_piece_id'], 'objectName' => 'Unity'],
            ];
        }

        return $positions;
    }
}
