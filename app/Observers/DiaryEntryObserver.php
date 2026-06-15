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
use App\Models\{DiaryEntry, DiaryEntryEvent, User};
use App\Services\{MailNotifier, PushNotifier};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

class DiaryEntryObserver {
    public function created(DiaryEntry $entry): void {
        $actor = Auth::user();
        DiaryEntryEvent::query()->create([
            'diary_entry_id' => $entry->id,
            'organization_id' => $entry->organization_id,
            'event' => 'order.created',
            'from_status' => null,
            'to_status' => $entry->status->slug(),
            'actor_user_id' => $actor instanceof User ? $actor->id : null,
            'actor_kind' => $actor instanceof User ? 'user' : 'system',
            'occurred_at' => CarbonImmutable::now(),
        ]);

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
