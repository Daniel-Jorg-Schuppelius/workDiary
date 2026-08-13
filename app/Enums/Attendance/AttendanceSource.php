<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum AttendanceSource: string implements HasLabel {
    use HasOptions;

    case Clock = 'clock';
    case Manual = 'manual';
    case Import = 'import';
    case AutoClose = 'auto_close';
    case Terminal = 'terminal';
    // MVP-534: Telefonstempeln — Anruf auf eine Stempel-MSN, Rufnummer = Ausweis.
    case Phone = 'phone';

    public function label(): string {
        return (string) __('attendance.source.' . $this->value);
    }
}
