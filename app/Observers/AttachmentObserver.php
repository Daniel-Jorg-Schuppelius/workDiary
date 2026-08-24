<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Attachment, DiaryEntry, User};
use App\Services\Notification\NotificationDispatcher;

class AttachmentObserver {
    /**
     * Anhang-Benachrichtigung an den Entry-Besitzer über den zentralen
     * Dispatcher (B7) — nur für Auftragsbuch-Anhänge fremder Uploader.
     */
    public function created(Attachment $attachment): void {
        $attachment->loadMissing('attachable');
        if (! $attachment->attachable instanceof DiaryEntry) {
            return;
        }

        /** @var DiaryEntry $entry */
        $entry = $attachment->attachable;
        if ((int) $entry->user_id === (int) $attachment->user_id) {
            return;
        }

        $entry->loadMissing('user');
        $owner = $entry->user;
        if (! $owner instanceof User) {
            return;
        }

        $name = (string) $attachment->original_name;
        $title = trim((string) $entry->title);
        app(NotificationDispatcher::class)->notify(NotificationEvent::DiaryAttachmentAdded, $entry, $owner, [
            'title' => $title !== '' ? $title : '#' . $entry->id,
            'message' => (string) __('notification.message.diary_attachment_added', ['name' => $name]),
            'message_key' => 'notification.message.diary_attachment_added',
            'message_params' => ['name' => $name],
            'url' => route('diary.show', $entry),
        ]);
    }
}
