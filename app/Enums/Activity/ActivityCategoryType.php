<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategoryType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Activity;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ActivityCategoryType: string implements HasLabel {
    use HasOptions;

    case Admin = 'admin';
    case Training = 'training';
    case Meeting = 'meeting';
    case Internal = 'internal';
    case Travel = 'travel';
    case Break_ = 'break';
    case Absence = 'absence';
    case Standby = 'standby';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.activity.category_type.' . $this->value);
    }
}
