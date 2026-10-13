<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillLineItems.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Billing\DocumentLineKind;
use App\Models\Billing\DeliveryNoteItem;
use App\Models\Contracts\DocumentLine;
use App\Models\Invoicing\Invoice;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\Model;

/**
 * Belegpositionen (MVP-865) als easybill-`items` (Phase 145): Preise in Cent,
 * Positionsrabatt in Prozent; ein Rabatt als Betrag steckt im Einzelpreis.
 * Optionale Angebotspositionen gehen als `POSITION_NOCALC` hinüber (stehen im
 * Beleg, zählen nicht zur Summe) — easybill kennt keine optionale Position.
 */
final class EasybillLineItems {
    /**
     * @param  iterable<DocumentLine&Model>  $lines
     * @return list<array<string, mixed>>
     */
    public static function from(iterable $lines, ?Percentage $fallbackRate = null, bool $absolute = false): array {
        $items = [];
        foreach ($lines as $line) {
            $text = trim((string) $line->getAttribute('description'));
            if (! $line->lineKind()->isPriced()) {
                $items[] = ['type' => 'TEXT', 'description' => $text];
                continue;
            }

            $quantity = (float) $line->lineQuantity();
            $amountDiscount = $line->discountAmount();
            $byAmount = $amountDiscount !== null && ! $amountDiscount->isZero() && $quantity !== 0.0;
            $percent = $byAmount ? 0.0 : (float) ($line->discountPercent()?->getNumericValue() ?? 0);
            $unitNet = $byAmount ? $line->netAmount()->toFloat() / $quantity : ($line->unitPrice()?->toFloat() ?? 0.0);

            $items[] = array_filter([
                'type' => (bool) $line->getAttribute('optional') ? 'POSITION_NOCALC' : 'POSITION',
                'description' => $text,
                'quantity' => round($absolute ? abs($quantity) : $quantity, 4),
                'unit' => $line->lineUnit(),
                'single_price_net' => round($unitNet * 100, 2),
                'vat_percent' => (float) (($line->taxRate() ?? $fallbackRate)?->getNumericValue() ?? 0),
                'discount' => $percent > 0 ? $percent : null,
                'discount_type' => $percent > 0 ? 'PERCENT' : null,
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        return $items;
    }

    /**
     * Belegrabatt der Rechnung: Prozent oder Betrag in Cent (positiv; die
     * Gutschrift kehrt das Vorzeichen über ihre Art).
     *
     * @return array<string, float|string>
     */
    public static function documentDiscount(Invoice $invoice): array {
        $percent = $invoice->discount_percent;
        if ($percent !== null && $percent->isPositive()) {
            return ['discount' => (float) $percent->getNumericValue(), 'discount_type' => 'PERCENT'];
        }
        $amount = $invoice->discount_amount;
        if ($amount !== null && ! $amount->isZero()) {
            return ['discount' => round(abs($amount->toFloat()) * 100, 2), 'discount_type' => 'AMOUNT'];
        }

        return [];
    }

    /**
     * Lieferscheinpositionen: Menge und Einheit ohne Preis.
     *
     * @param  iterable<DeliveryNoteItem>  $lines
     * @return list<array<string, mixed>>
     */
    public static function quantities(iterable $lines): array {
        $items = [];
        foreach ($lines as $line) {
            $text = trim($line->description);
            if ($line->line_kind !== DocumentLineKind::Item) {
                $items[] = ['type' => 'TEXT', 'description' => $text];
                continue;
            }
            $items[] = array_filter([
                'type' => 'POSITION',
                'number' => $line->sku,
                'description' => $text,
                'quantity' => round((float) $line->quantity, 4),
                'unit' => $line->unit,
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        return $items;
    }
}
