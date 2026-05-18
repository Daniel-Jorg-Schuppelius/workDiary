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

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\Timesheet;
use App\Models\User;

class PushNotifier
{
    public function __construct(protected WebPushService $webPush) {}

    public function newComment(Comment $comment): void
    {
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
            'body' => mb_substr((string) $comment->body, 0, (int) setting('notifications.push.body_truncate', 120)),
            'url' => route('diary.show', $entry),
            'tag' => 'comment-'.$entry->id,
        ]);
    }

    public function newAttachment(Attachment $att): void
    {
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
            'tag' => 'attachment-'.$entry->id,
        ]);
    }

    public function emergencyAssigned(EmergencyAssignment $assignment): void
    {
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
            'body' => optional($assignment->start_at)->format('d.m.Y H:i').' – '.($assignment->reason ?: ''),
            'url' => route('week.index'),
            'tag' => 'assignment-'.$assignment->id,
        ]);
    }

    public function diaryProblem(DiaryEntry $entry): void
    {
        if ((int) $entry->status !== 3) {
            return;
        }
        $recipients = User::role([User::ROLE_ADMIN, User::ROLE_CALLCENTER])
            ->where('id', '!=', $entry->user_id)
            ->with('pushSubscriptions')
            ->get();
        $payload = [
            'title' => __('Problem-Eintrag'),
            'body' => mb_substr((string) $entry->content, 0, (int) setting('notifications.push.body_truncate', 120)),
            'url' => route('diary.show', $entry),
            'tag' => 'problem-'.$entry->id,
        ];
        foreach ($recipients as $u) {
            $this->webPush->sendToUser($u, $payload);
        }
    }

    public function timesheetSigned(Timesheet $timesheet): void
    {
        $timesheet->loadMissing(['user.pushSubscriptions', 'project']);
        /** @var User|null $owner */
        $owner = $timesheet->user;
        if (! $owner) {
            return;
        }
        $this->webPush->sendToUser($owner, [
            'title' => __('Stundenzettel signiert'),
            'body' => ($timesheet->project?->name ?? '').' · '.$timesheet->work_date->format('d.m.Y'),
            'url' => route('projects.timesheets.show', [$timesheet->project_id, $timesheet->id]),
            'tag' => 'timesheet-'.$timesheet->id,
        ]);
    }
}
