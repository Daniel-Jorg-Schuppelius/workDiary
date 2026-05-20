<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Timesheet;
use App\Services\PushNotifier;

class TimesheetObserver
{
    public function updated(Timesheet $timesheet): void
    {
        if (! $timesheet->wasChanged('status')) {
            return;
        }

        if ($timesheet->status === TimesheetStatus::Signed) {
            app(PushNotifier::class)->timesheetSigned($timesheet);
        }
    }
}
