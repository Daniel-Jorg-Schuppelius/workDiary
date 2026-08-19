<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediumType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Access;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art des Zutrittsmediums (Feature 092). */
enum AccessMediumType: string implements HasLabel {
    use HasOptions;

    case Transponder = 'transponder';
    case Card = 'card';
    case Code = 'code';

    public function label(): string {
        return (string) __('enums.access.medium_type.' . $this->value);
    }
}
