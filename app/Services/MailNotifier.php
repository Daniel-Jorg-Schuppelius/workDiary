<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailNotifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Mail\CommentCreatedMail;
use App\Mail\DiaryStatusChangedMail;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MailNotifier {
    public function commentCreated(Comment $comment): void {
        if (! $this->enabled()) {
            return;
        }

        /** @var DiaryEntry|null $entry */
        $entry = $comment->diaryEntry()->with('user', 'comments.user')->first();
        if (! $entry) {
            return;
        }

        $recipients = $this->commentRecipients($entry, $comment);
        if ($recipients->isEmpty()) {
            return;
        }

        Mail::to($recipients->all())->queue(new CommentCreatedMail($comment));
    }

    public function diaryStatusChanged(DiaryEntry $entry, ?int $oldStatus, int $newStatus): void {
        if (! $this->enabled()) {
            return;
        }
        // Nur bei Wechsel auf "Problem" (3) oder "Erledigt" (-1) benachrichtigen.
        if (! in_array($newStatus, [3, -1], true)) {
            return;
        }
        if ($oldStatus === $newStatus) {
            return;
        }

        $entry->loadMissing('user');
        /** @var User|null $owner */
        $owner = $entry->user;
        if (! $owner || ! $owner->email || $owner->id === (int) Auth::id()) {
            return;
        }

        Mail::to($owner->email)->queue(new DiaryStatusChangedMail($entry, $oldStatus, $newStatus));
    }

    /**
     * Empfänger-Liste für Kommentare: Entry-Owner + andere Kommentatoren, ohne Auslöser.
     *
     * @return Collection<int,string>
     */
    private function commentRecipients(DiaryEntry $entry, Comment $comment): Collection {
        $authorId = (int) $comment->user_id;

        $userIds = $entry->comments
            ->pluck('user_id')
            ->push($entry->user_id)
            ->unique()
            ->reject(fn($id) => (int) $id === $authorId)
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $userIds)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->values();
    }

    private function enabled(): bool {
        return (bool) config('app.mail_notifications_enabled', false);
    }
}
