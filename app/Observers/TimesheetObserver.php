<?php

namespace App\Observers;

use App\Models\Timesheet;
use App\Services\PushNotifier;

class TimesheetObserver
{
    public function updated(Timesheet $timesheet): void
    {
        if (! $timesheet->wasChanged('status')) {
            return;
        }

        if ($timesheet->status === Timesheet::STATUS_SIGNED) {
            app(PushNotifier::class)->timesheetSigned($timesheet);
        }
    }
}
