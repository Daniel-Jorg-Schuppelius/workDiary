<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuantityKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Mengenart einer Stücklisten-/Rezepturposition (Feature 047, MVP-061).
 *
 * - Fixed:   feste Menge je Auftrag (unabhängig von der Sollmenge)
 * - PerUnit: Menge pro Stück (skaliert mit der Sollmenge)
 * - Ratio:   Anteil im Rezept (die Sollmenge wird über alle Ratio-Positionen
 *            gemäß ihrer Anteile aufgeteilt; z. B. Wasser 1 : Pulver 3)
 */
enum QuantityKind: string implements HasLabel {
    use HasOptions;

    case Fixed = 'fixed';
    case PerUnit = 'per_unit';
    case Ratio = 'ratio';

    public function label(): string {
        return __('manufacturing.quantity_kind.' . $this->value);
    }
}
