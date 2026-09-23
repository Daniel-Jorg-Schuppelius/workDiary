<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeProration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Anteilsregel bei Eintritt/Austritt/Tarifwechsel (MVP-849): volle Periode oder taggenau nach aktiven Kalendertagen. */
enum ClubFeeProration: string implements HasLabel {
    use HasOptions;

    case FullPeriod = 'full';
    case Daily = 'daily';

    public function label(): string {
        return (string) __('enums.club.fee-proration.' . $this->value);
    }
}
