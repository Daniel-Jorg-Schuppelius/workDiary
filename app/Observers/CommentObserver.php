<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Comment, DiaryEntry, User};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Setting;

class CommentObserver {
    /**
     * Kommentar-Benachrichtigung über den zentralen Dispatcher (B7): Empfänger
     * wie zuvor Entry-Besitzer + andere Kommentatoren ohne den Auslöser;
     * Kanäle/Präferenzen/Ruhezeiten regelt der Dispatcher. Kommentare an
     * anderen Commentables (z. B. TimeEntry) haben weiter keinen eigenen Fluss.
     */
    public function created(Comment $comment): void {
        $comment->loadMissing('commentable', 'user');
        if (! $comment->commentable instanceof DiaryEntry) {
            return;
        }

        /** @var DiaryEntry $entry */
        $entry = $comment->commentable;
        $entry->loadMissing('comments');

        $authorId = (int) $comment->user_id;
        $recipientIds = $entry->comments
            ->pluck('user_id')
            ->push($entry->user_id)
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->reject(fn(int $id): bool => $id === 0 || $id === $authorId)
            ->values();
        if ($recipientIds->isEmpty()) {
            return;
        }

        $excerpt = mb_substr((string) $comment->body, 0, (int) Setting::get('notifications.push.body_truncate', 120));
        $actorName = trim((string) ($comment->user->name ?? ''));
        // Externe Teilnehmer haben keinen User — render-time via Trans::or.
        $actorParam = $actorName !== '' ? $actorName : ['key' => 'notification.message.unknown_actor'];
        $payload = [
            'title' => $this->entryLabel($entry),
            'message' => (string) __('notification.message.diary_comment_created', [
                'actor' => $actorName !== '' ? $actorName : (string) __('notification.message.unknown_actor'),
                'excerpt' => $excerpt,
            ]),
            'message_key' => 'notification.message.diary_comment_created',
            'message_params' => ['actor' => $actorParam, 'excerpt' => $excerpt],
            'url' => route('diary.show', $entry),
        ];

        $dispatcher = app(NotificationDispatcher::class);
        foreach (User::query()->whereIn('id', $recipientIds->all())->get() as $recipient) {
            $dispatcher->notify(NotificationEvent::DiaryCommentCreated, $entry, $recipient, $payload);
        }
    }

    private function entryLabel(DiaryEntry $entry): string {
        $title = trim((string) $entry->title);

        return $title !== '' ? $title : '#' . $entry->id;
    }
}
