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

use App\Models\{TimeEntry, User};
use App\Services\Diary\OrderService;

class TimeEntryObserver {
    public function created(TimeEntry $entry): void {
        if ($entry->diary_entry_id === null) {
            return;
        }

        $actor = $entry->user;
        $diaryEntry = $entry->diaryEntry;
        if ($actor instanceof User && $diaryEntry !== null) {
            app(OrderService::class)->startFromTimeEntry($diaryEntry, $actor);
        }
    }

    public function saved(TimeEntry $entry): void {
        $entry->timesheet?->recalcTotals();
    }

    public function deleted(TimeEntry $entry): void {
        $entry->timesheet?->recalcTotals();
    }
}
