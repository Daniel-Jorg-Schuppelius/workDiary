<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Timesheet;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TimesheetStatus: string implements HasLabel
{
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Signed = 'signed';
    case Locked = 'locked';

    public function label(): string
    {
        return (string) __('enums.timesheet.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::Signed => 'success',
            self::Locked => 'warning',
        };
    }
}
