<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PushNotifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Enums\Diary\Status;
use App\Enums\User\UserRole;
use App\Models\{Attachment, Comment, DiaryEntry, EmergencyAssignment, Timesheet, User};
use App\Support\Setting;

class PushNotifier {
    public function __construct(protected WebPushService $webPush) {}

    public function newComment(Comment $comment): void {
        /** @var DiaryEntry|null $entry */
        $entry = $comment->commentable instanceof DiaryEntry ? $comment->commentable : null;
        if (! $entry || ! $entry->user_id || $entry->user_id === $comment->user_id) {
            return;
        }
        $entry->loadMissing('user.pushSubscriptions');
        /** @var User|null $owner */
        $owner = $entry->user;
        if (! $owner) {
            return;
        }
        $this->webPush->sendToUser($owner, [
            'title' => __('Neuer Kommentar'),
            'body' => mb_substr((string) $comment->body, 0, (int) Setting::get('notifications.push.body_truncate', 120)),
            'url' => route('diary.show', $entry),
            'tag' => 'comment-' . $entry->id,
        ]);
    }

    public function newAttachment(Attachment $att): void {
        $att->loadMissing('attachable');
        $attachable = $att->attachable;
        if (! $attachable instanceof DiaryEntry) {
            return;
        }
        $attachable->loadMissing('user.pushSubscriptions');
        $entry = $attachable;
        if ($entry->user_id === $att->user_id) {
            return;
        }
        /** @var User|null $owner */
        $owner = $entry->user;
        if (! $owner) {
            return;
        }
        $this->webPush->sendToUser($owner, [
            'title' => __('Neuer Anhang'),
            'body' => (string) $att->original_name,
            'url' => route('diary.show', $entry),
            'tag' => 'attachment-' . $entry->id,
        ]);
    }

    public function emergencyAssigned(EmergencyAssignment $assignment): void {
        if (! $assignment->user_id) {
            return;
        }
        $assignment->loadMissing('user.pushSubscriptions');
        /** @var User|null $user */
        $user = $assignment->user;
        if (! $user) {
            return;
        }
        $this->webPush->sendToUser($user, [
            'title' => __('Notdienst zugewiesen'),
            'body' => optional($assignment->start_at)->format('d.m.Y H:i') . ' – ' . ($assignment->reason ?: ''),
            'url' => route('week.index'),
            'tag' => 'assignment-' . $assignment->id,
        ]);
    }

    public function diaryProblem(DiaryEntry $entry): void {
        if ($entry->status !== Status::Problem) {
            return;
        }
        $recipients = User::role([UserRole::Admin->value, UserRole::Callcenter->value])
            ->where('id', '!=', $entry->user_id)
            ->with('pushSubscriptions')
            ->get();
        $payload = [
            'title' => __('Problem-Eintrag'),
            'body' => mb_substr((string) $entry->content, 0, (int) Setting::get('notifications.push.body_truncate', 120)),
            'url' => route('diary.show', $entry),
            'tag' => 'problem-' . $entry->id,
        ];
        foreach ($recipients as $u) {
            $this->webPush->sendToUser($u, $payload);
        }
    }

    public function timesheetSigned(Timesheet $timesheet): void {
        $timesheet->loadMissing(['user.pushSubscriptions', 'project']);
        /** @var User|null $owner */
        $owner = $timesheet->user;
        $project = $timesheet->project;
        if (! $owner || ! $project) {
            return;
        }
        $this->webPush->sendToUser($owner, [
            'title' => __('Stundenzettel signiert'),
            'body' => $project->name . ' · ' . $timesheet->work_date->format('d.m.Y'),
            'url' => route('projects.timesheets.show', [$project, $timesheet]),
            'tag' => 'timesheet-' . $timesheet->id,
        ]);
    }

    /**
     * Chat: benachrichtigt bei Direktnachrichten alle anderen Mitglieder und in
     * Kanälen/Gruppen die per @Name erwähnten Mitglieder (sofern nicht
     * stummgeschaltet). Verschickt Web-Push an deren Geräte.
     */
    public function chatMessage(\App\Models\Chat\Message $message): void {
        if (! $message->user_id) {
            return;
        }
        $message->loadMissing(['channel.members.pushSubscriptions', 'user']);
        $channel = $message->channel;
        if ($channel === null) {
            return;
        }

        $body = (string) $message->body;
        $sender = $message->user->name ?? __('Unbekannt');
        $title = $channel->isDirect() ? $sender : '#' . ($channel->name ?? __('Chat')) . ' · ' . $sender;

        // Stummgeschaltete Mitglieder per Pivot-Query ausschließen (kein Pivot-Property-Zugriff).
        $mutedIds = $channel->members()->wherePivotNotNull('muted_at')->pluck('users.id')->all();

        $recipients = $channel->members->filter(function (User $m) use ($message, $channel, $body, $mutedIds): bool {
            if ($m->id === $message->user_id || in_array($m->id, $mutedIds, true)) {
                return false;
            }
            // Direktnachricht: immer benachrichtigen; sonst nur bei @Mention.
            return $channel->isDirect() || mb_stripos($body, '@' . $m->name) !== false;
        });

        $truncate = (int) Setting::get('notifications.push.body_truncate', 120);
        foreach ($recipients as $m) {
            $this->webPush->sendToUser($m, [
                'title' => $title,
                'body' => mb_substr($body !== '' ? $body : __('[Anhang]'), 0, $truncate),
                'url' => route('chat.show', $channel),
                'tag' => 'chat-' . $channel->id,
            ]);
        }
    }
}
