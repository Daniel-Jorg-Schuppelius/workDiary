<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Positionsart einer LV-Position (Feature 049, MVP-082). Abgeleitet aus den
 * GAEB-Itemkennzeichen (Provis/Alternative/Lump-Sum etc.).
 *
 * - Standard:    Normalposition mit Menge
 * - Alternative: Wahl-/Alternativposition (GAEB Alternative)
 * - Optional:    Bedarfsposition (Provisional/Eventualposition)
 * - LumpSum:     Pauschalposition ohne mengenabhängige Abrechnung
 * - Markup:      Zuschlagsposition (prozentual auf Bezugspositionen)
 * - Note:        reine Hinweis-/Textposition ohne Menge
 */
enum BoqItemType: string implements HasLabel {
    use HasOptions;

    case Standard = 'standard';
    case Alternative = 'alternative';
    case Optional = 'optional';
    case LumpSum = 'lump_sum';
    case Markup = 'markup';
    case Note = 'note';

    public function label(): string {
        return __('gaeb.item.type.' . $this->value);
    }

    /** Wird diese Positionsart regulär mengen- und preisbasiert abgerechnet? */
    public function isBillable(): bool {
        return match ($this) {
            self::Note, self::Optional, self::Alternative => false,
            default => true,
        };
    }
}
