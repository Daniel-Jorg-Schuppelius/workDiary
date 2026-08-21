<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyBasis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Warranty;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Rechtsgrundlage der Gewährleistungsfrist (Feature 115, MVP-604).
 *
 * Die Grundlage bleibt am Datensatz, auch wenn das Enddatum von Hand
 * verschoben wurde — sonst ist Monate später nicht mehr erkennbar, ob vier
 * Jahre VOB oder fünf Jahre BGB vereinbart waren.
 */
enum WarrantyBasis: string implements HasLabel {
    use HasOptions;

    case Bgb5Years = 'bgb_5y';
    case Vob4Years = 'vob_4y';
    case Custom = 'custom';

    public function label(): string {
        return (string) __('enums.warranty_basis.' . $this->value);
    }

    /** Regel-Laufzeit in Monaten; `Custom` hat keine — dort zählt das Enddatum. */
    public function months(): ?int {
        return match ($this) {
            self::Bgb5Years => 60,
            self::Vob4Years => 48,
            self::Custom => null,
        };
    }
}
