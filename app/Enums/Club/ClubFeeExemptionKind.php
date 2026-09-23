<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeExemptionKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Befreiung (MVP-849): vollständig oder prozentuale Ermäßigung im Zeitraum — nie automatisch aus einer Mitgliedschaftspause. */
enum ClubFeeExemptionKind: string implements HasLabel {
    use HasOptions;

    case Exemption = 'exemption';
    case Reduction = 'reduction';

    public function label(): string {
        return (string) __('enums.club.fee-exemption-kind.' . $this->value);
    }
}
