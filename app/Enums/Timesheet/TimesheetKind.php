<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Timesheet;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TimesheetKind: string implements HasLabel
{
    use HasOptions;

    case Project = 'project';
    case PersonalDay = 'personal_day';

    public function label(): string
    {
        return (string) __('enums.timesheet.kind.'.$this->value);
    }
}
