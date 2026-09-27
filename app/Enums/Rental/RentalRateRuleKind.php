<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateRuleKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer Mietpreisregel (MVP-950). */
enum RentalRateRuleKind: string implements HasLabel {
    use HasOptions;

    case Season = 'season';
    case Weekday = 'weekday';
    case Utilization = 'utilization';

    public function label(): string {
        return (string) __('rental.rule.kind.' . $this->value);
    }
}
