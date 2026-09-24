<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StartOrderFromTimeEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Diary;

use App\Events\Time\TimeEntryCreated;
use App\Models\Platform\User;
use App\Services\Diary\OrderService;

/** Erster Zeiteintrag am geplanten Auftrag nimmt ihn an und startet ihn. */
final class StartOrderFromTimeEntry {
    public function __construct(private readonly OrderService $orders) {}

    public function handle(TimeEntryCreated $event): void {
        $entry = $event->entry;
        if ($entry->diary_entry_id === null) {
            return;
        }
        $actor = $entry->user;
        $diaryEntry = $entry->diaryEntry;
        if ($actor instanceof User && $diaryEntry !== null) {
            $this->orders->startFromTimeEntry($diaryEntry, $actor);
        }
    }
}
