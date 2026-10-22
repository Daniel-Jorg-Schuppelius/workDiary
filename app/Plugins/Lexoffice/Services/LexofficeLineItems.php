<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeLineItems.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Contracts\DocumentLine;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\Model;

/**
 * Belegpositionen (MVP-865) als Lexoffice-`lineItems` (Phase 145): Steuersatz
 * und Rabatt je Zeile. Ein Positionsrabatt als Betrag steckt im Einzelpreis,
 * weil Lexoffice je Zeile nur Prozent kennt; Einzelpreis und Menge mit bis zu
 * vier Nachkommastellen.
 */
final class LexofficeLineItems {
    /**
     * @param  iterable<DocumentLine&Model>  $lines
     * @param  bool  $discountInPrice  auch Prozentrabatte in den Einzelpreis und kein Feld
     *                                 `discountPercentage`: Gutschriften kennen laut Lexware-Doku
     *                                 keinen Positionsrabatt, er fiele still weg
     * @return list<array<string, mixed>>
     */
    public static function from(iterable $lines, bool $withOptional = false, ?Percentage $fallbackRate = null, bool $discountInPrice = false): array {
        $items = [];
        foreach ($lines as $line) {
            $name = trim((string) $line->getAttribute('description')) ?: (string) __('Position');
            if (! $line->lineKind()->isPriced()) {
                $items[] = ['type' => 'text', 'name' => mb_substr($name, 0, 255), 'description' => mb_strlen($name) > 255 ? $name : null];
                continue;
            }

            $quantity = (float) $line->lineQuantity();
            $amountDiscount = $line->discountAmount();
            $percentDiscount = (float) ($line->discountPercent()?->getNumericValue() ?? 0);
            $byAmount = $quantity !== 0.0 && (($amountDiscount !== null && ! $amountDiscount->isZero()) || ($discountInPrice && $percentDiscount !== 0.0));
            $item = [
                'type' => 'custom',
                'name' => mb_substr($name, 0, 255),
                'quantity' => round($quantity, 4),
                'unitName' => $line->lineUnit() ?: (string) __('invoicing.unit_piece'),
                'unitPrice' => [
                    'currency' => $line->lineCurrency()->value,
                    'netAmount' => round($byAmount ? $line->netAmount()->toFloat() / $quantity : ($line->unitPrice()?->toFloat() ?? 0.0), 4),
                    'taxRatePercentage' => (float) (($line->taxRate() ?? $fallbackRate)?->getNumericValue() ?? 0),
                ],
                'discountPercentage' => $discountInPrice ? null : ($byAmount ? 0.0 : $percentDiscount),
            ];
            if (mb_strlen($name) > 255) {
                $item['description'] = $name;
            }
            if ($withOptional) {
                $item['optional'] = (bool) $line->getAttribute('optional');
            }
            $items[] = $item;
        }

        return array_map(static fn (array $item): array => array_filter($item, static fn ($value): bool => $value !== null), $items);
    }

    /**
     * Steuerart: ohne steuerpflichtige Zeile `vatfree` (Kleinunternehmer), sonst
     * die Voreinstellung des Plugins.
     *
     * @param  iterable<DocumentLine&Model>  $lines
     * @param  array<string, mixed>  $defaults
     */
    public static function taxType(iterable $lines, array $defaults, ?Percentage $fallbackRate = null): string {
        foreach ($lines as $line) {
            if ($line->lineKind()->isPriced() && ! (($line->taxRate() ?? $fallbackRate)?->isZero() ?? true)) {
                return (string) ($defaults['default_tax_type'] ?? 'net');
            }
        }

        return 'vatfree';
    }
}
