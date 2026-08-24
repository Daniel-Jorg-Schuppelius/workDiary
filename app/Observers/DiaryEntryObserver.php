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
use App\Enums\Notification\NotificationEvent;
use App\Models\{DiaryEntry, DiaryEntryEvent, User};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Setting;
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

        if ($entry->status === Status::Problem) {
            // Ersteller meldet das Problem selbst — nur die Default-Rollen
            // (Admin/Callcenter) der Regel, keine „betroffene Person".
            $this->notifyStatus($entry, NotificationEvent::DiaryProblem, null);
        }
    }

    public function updated(DiaryEntry $entry): void {
        if (! $entry->wasChanged('status')) {
            return;
        }

        $entry->loadMissing('user');
        $owner = $entry->user;
        // Der Besitzer wird nur benachrichtigt, wenn er nicht selbst auslöst.
        $affected = $owner instanceof User && $owner->id !== (int) Auth::id() ? $owner : null;

        if ($entry->status === Status::Problem) {
            $this->notifyStatus($entry, NotificationEvent::DiaryProblem, $affected);
        } elseif ($entry->status === Status::Done && $affected !== null) {
            $this->notifyStatus($entry, NotificationEvent::DiaryCompleted, $affected);
        }
    }

    /**
     * Polymorphic comments have no database-level FK cascade; remove them
     * manually when their owning DiaryEntry is deleted.
     */
    public function deleting(DiaryEntry $entry): void {
        $entry->comments()->delete();
    }

    /** Statuswechsel-Benachrichtigung über den zentralen Dispatcher (B7). */
    private function notifyStatus(DiaryEntry $entry, NotificationEvent $event, ?User $affected): void {
        $payload = [
            'title' => $this->entryLabel($entry),
            'url' => route('diary.show', $entry),
        ];
        if ($event === NotificationEvent::DiaryProblem) {
            // Wie der Legacy-Push: Inhalt des Eintrags als Kurztext (Nutzer-
            // text, bewusst ohne Lang-Key — es gibt nichts zu übersetzen).
            $payload['message'] = mb_substr((string) $entry->content, 0, (int) Setting::get('notifications.push.body_truncate', 120));
        } else {
            $payload['message'] = (string) __('notification.message.diary_completed');
            $payload['message_key'] = 'notification.message.diary_completed';
            $payload['message_params'] = [];
        }

        app(NotificationDispatcher::class)->notify($event, $entry, $affected, $payload);
    }

    private function entryLabel(DiaryEntry $entry): string {
        $title = trim((string) $entry->title);

        return $title !== '' ? $title : '#' . $entry->id;
    }
}
