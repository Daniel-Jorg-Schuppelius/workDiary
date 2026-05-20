<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Diary\Status;
use App\Models\DiaryEntry;
use App\Services\MailNotifier;
use App\Services\PushNotifier;

class DiaryEntryObserver {
    public function created(DiaryEntry $entry): void {
        app(PushNotifier::class)->diaryProblem($entry);
    }

    public function updated(DiaryEntry $entry): void {
        if (! $entry->wasChanged('status')) {
            return;
        }

        app(PushNotifier::class)->diaryProblem($entry);
        $original = $entry->getOriginal('status');
        $oldValue = $original instanceof Status
            ? $original->value
            : ($original !== null ? (int) $original : null);
        app(MailNotifier::class)->diaryStatusChanged(
            $entry,
            $oldValue,
            $entry->status->value,
        );
    }

    /**
     * Polymorphic comments have no database-level FK cascade; remove them
     * manually when their owning DiaryEntry is deleted.
     */
    public function deleting(DiaryEntry $entry): void {
        $entry->comments()->delete();
    }
}
