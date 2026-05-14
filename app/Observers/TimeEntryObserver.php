<?php

namespace App\Observers;

use App\Models\TimeEntry;

class TimeEntryObserver
{
    public function saved(TimeEntry $entry): void
    {
        $entry->timesheet?->recalcTotals();
    }

    public function deleted(TimeEntry $entry): void
    {
        $entry->timesheet?->recalcTotals();
    }
}
