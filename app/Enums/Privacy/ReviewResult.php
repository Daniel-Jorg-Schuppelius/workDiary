<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReviewResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Ergebnis einer TOM-Wirksamkeitspruefung (Art. 32 Abs. 1 lit. d). */
enum ReviewResult: string {
    case Effective = 'effective';      // wirksam
    case Deviation = 'deviation';      // Abweichung – Folgemaßnahme nötig
    case Ineffective = 'ineffective';  // unwirksam

    public function label(): string {
        return match ($this) {
            self::Effective => __('Wirksam'),
            self::Deviation => __('Abweichung'),
            self::Ineffective => __('Unwirksam'),
        };
    }
}
