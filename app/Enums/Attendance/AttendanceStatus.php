<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';
    case AutoClosed = 'auto_closed';
    case Adjusted = 'adjusted';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('attendance.status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'info',
            self::Closed => 'success',
            self::AutoClosed => 'warning',
            self::Adjusted => 'warning',
            self::Cancelled => 'ghost',
        };
    }
}
