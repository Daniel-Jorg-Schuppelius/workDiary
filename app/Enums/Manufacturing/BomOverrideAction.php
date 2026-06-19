<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BomOverrideAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

/**
 * Wirkung eines Varianten-Stücklisten-Overrides (Feature 047, MVP-061). Die
 * Variante darf Basis-Positionen über ihren stabilen `position_code`
 * deaktivieren, Mengen überschreiben oder neue Positionen hinzufügen.
 */
enum BomOverrideAction: string {
    case Disable = 'disable';
    case OverrideQty = 'override_qty';
    case Add = 'add';

    public function label(): string {
        return __('manufacturing.bom_override.' . $this->value);
    }
}
