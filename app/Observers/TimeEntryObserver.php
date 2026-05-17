<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
