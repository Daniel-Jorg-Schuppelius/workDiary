<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\OpenIssue;

use App\Enums\OpenIssue\{OpenIssueEventType, OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Exceptions\InvalidOpenIssueTransitionException;
use App\Models\{OpenIssue, OpenIssueEvent, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Domain-Service für Offene Punkte (Snagging Items / Restpunkte).
 *
 * Verantwortlich für Anlage, Statusübergänge, Zuweisung und Audit-Trail.
 * Controller dürfen nicht direkt am Model arbeiten — alle schreibenden
 * Operationen laufen über diesen Service, damit die Statemachine, die
 * Pflichtfelder und der Audit-Trail konsistent erzwungen werden.
 */
class OpenIssueService {
    /**
     * Erlaubte Statusübergänge gemäß ../WorkDiary-Architecture/offene-punkte.md §3.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'open' => ['inProgress', 'done', 'wontDo'],
        'inProgress' => ['blocked', 'done', 'wontDo'],
        'blocked' => ['inProgress'],
        'done' => ['reopened'],
        'wontDo' => ['reopened'],
        'reopened' => ['open', 'inProgress'],
    ];

    /**
     * Legt einen neuen Offenen Punkt an.
     *
     * @param  Model  $subject  z. B. DiaryEntry, Project, Customer …
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $subject, User $creator, array $attributes): OpenIssue {
        $severity = $this->parseSeverity($attributes['severity'] ?? OpenIssueSeverity::Low->value);
        $assigneeId = isset($attributes['assignee_user_id'])
            ? (int) $attributes['assignee_user_id']
            : null;
        $dueAt = isset($attributes['due_at']) ? Carbon::parse($attributes['due_at']) : null;

        // Critical-Issues brauchen Frist; wenn keine angegeben, Default
        // gemäß ../WorkDiary-Architecture/offene-punkte.md §4 auf now+7d setzen statt zu blocken.
        if ($severity === OpenIssueSeverity::Critical && $dueAt === null) {
            $dueAt = Carbon::now()->addDays(7);
        }

        // High/Critical brauchen einen Assignee. Ist keiner gesetzt, fällt
        // die Verantwortung auf den Ersteller — besser als „nicht zugewiesen".
        if ($severity->requiresAssignee() && $assigneeId === null) {
            $assigneeId = $creator->id;
        }

        $issue = DB::transaction(function () use ($subject, $creator, $attributes, $severity, $assigneeId, $dueAt): OpenIssue {
            $issue = OpenIssue::query()->create([
                'organization_id' => $subject->getAttribute('organization_id') ?: $creator->organization_id,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'source_type' => ($attributes['source_type'] ?? OpenIssueSource::Manual->value),
                'source_ref_id' => $attributes['source_ref_id'] ?? null,
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'] ?? null,
                'severity' => $severity->value,
                'status' => OpenIssueStatus::Open->value,
                'assignee_user_id' => $assigneeId,
                'due_at' => $dueAt,
                'visibility' => ($attributes['visibility'] ?? OpenIssueVisibility::Internal->value),
                'created_by_user_id' => $creator->id,
            ]);

            $this->record($issue, OpenIssueEventType::Created, $creator, [
                'severity' => $issue->severity->value,
                'assignee_user_id' => $issue->assignee_user_id,
                'due_at' => $issue->due_at?->toIso8601String(),
            ]);

            if ($assigneeId !== null) {
                $this->record($issue, OpenIssueEventType::Assigned, $creator, [
                    'assignee_user_id' => $assigneeId,
                ]);
            }

            return $issue->fresh(['events']) ?? $issue;
        });

        // Benachrichtigung (MVP-018) erst nach Commit — der Dispatcher darf
        // die fachliche Transaktion nie beeinflussen.
        if ($issue->assignee_user_id !== null && (int) $issue->assignee_user_id !== (int) $creator->id) {
            $this->notifyAssigned($issue, $creator);
        }

        return $issue;
    }

    public function assign(OpenIssue $issue, ?User $assignee, User $actor): OpenIssue {
        $newId = $assignee?->id;
        if ($issue->assignee_user_id === $newId) {
            return $issue;
        }

        $issue->update(['assignee_user_id' => $newId]);
        $this->record($issue, OpenIssueEventType::Assigned, $actor, [
            'assignee_user_id' => $newId,
        ]);

        if ($assignee !== null && (int) $assignee->id !== (int) $actor->id) {
            $this->notifyAssigned($issue, $actor);
        }

        return $issue;
    }

    /** Benachrichtigung „Offener Punkt zugewiesen" (MVP-018, additiv). */
    private function notifyAssigned(OpenIssue $issue, User $actor): void {
        $assignee = $issue->assignee ?? User::query()->find($issue->assignee_user_id);
        if ($assignee === null) {
            return;
        }

        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::OpenIssueAssigned,
            $issue,
            $assignee,
            [
                'title' => (string) $issue->title,
                'message' => (string) __('notification.message.issue_assigned', ['actor' => $actor->name]),
                'url' => \App\Support\NotificationLinks::openIssueUrl($issue),
            ],
        );
    }

    public function start(OpenIssue $issue, User $actor): OpenIssue {
        $this->transition($issue, OpenIssueStatus::InProgress, $actor, OpenIssueEventType::Started);

        if ($issue->assignee_user_id === null) {
            $this->assign($issue, $actor, $actor);
        }

        return $issue->refresh();
    }

    public function block(OpenIssue $issue, User $actor, string $reason): OpenIssue {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Begründung ist beim Blockieren Pflicht.');
        }

        $this->transition($issue, OpenIssueStatus::Blocked, $actor, OpenIssueEventType::Blocked, [
            'reason' => $reason,
        ]);

        return $issue->refresh();
    }

    public function unblock(OpenIssue $issue, User $actor): OpenIssue {
        $this->transition($issue, OpenIssueStatus::InProgress, $actor, OpenIssueEventType::Unblocked);

        return $issue->refresh();
    }

    public function complete(OpenIssue $issue, User $actor, string $resolution): OpenIssue {
        if (trim($resolution) === '') {
            throw new InvalidArgumentException('Lösungs-Notiz ist beim Abschluss Pflicht.');
        }

        $issue->forceFill([
            'closed_at' => Carbon::now(),
            'closed_by_user_id' => $actor->id,
            'closed_reason' => $resolution,
        ])->save();

        $this->transition($issue, OpenIssueStatus::Done, $actor, OpenIssueEventType::Completed, [
            'resolution' => $resolution,
        ]);

        return $issue->refresh();
    }

    public function wontDo(OpenIssue $issue, User $actor, string $reason): OpenIssue {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Begründung ist bei „wird nicht erledigt" Pflicht.');
        }

        $issue->forceFill([
            'closed_at' => Carbon::now(),
            'closed_by_user_id' => $actor->id,
            'closed_reason' => $reason,
        ])->save();

        $this->transition($issue, OpenIssueStatus::WontDo, $actor, OpenIssueEventType::WontDo, [
            'reason' => $reason,
        ]);

        return $issue->refresh();
    }

    public function reopen(OpenIssue $issue, User $actor, string $reason): OpenIssue {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Begründung ist beim Wiedereröffnen Pflicht.');
        }

        $issue->forceFill([
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closed_reason' => null,
        ])->save();

        $this->transition($issue, OpenIssueStatus::Reopened, $actor, OpenIssueEventType::Reopened, [
            'reason' => $reason,
        ]);

        return $issue->refresh();
    }

    public function changeDueDate(OpenIssue $issue, ?Carbon $dueAt, User $actor): OpenIssue {
        $old = $issue->due_at?->toIso8601String();
        $issue->update(['due_at' => $dueAt]);
        $this->record($issue, OpenIssueEventType::DueDateChanged, $actor, [
            'old' => $old,
            'new' => $dueAt?->toIso8601String(),
        ]);

        return $issue;
    }

    public function changeSeverity(OpenIssue $issue, OpenIssueSeverity $severity, User $actor): OpenIssue {
        if ($issue->severity === $severity) {
            return $issue;
        }

        $old = $issue->severity->value;
        $issue->update(['severity' => $severity->value]);
        $this->record($issue, OpenIssueEventType::SeverityChanged, $actor, [
            'old' => $old,
            'new' => $severity->value,
        ]);

        return $issue;
    }

    public function changeVisibility(OpenIssue $issue, OpenIssueVisibility $visibility, User $actor): OpenIssue {
        if ($issue->visibility === $visibility) {
            return $issue;
        }

        $old = $issue->visibility->value;
        $issue->update(['visibility' => $visibility->value]);
        $this->record($issue, OpenIssueEventType::VisibilityChanged, $actor, [
            'old' => $old,
            'new' => $visibility->value,
        ]);

        return $issue;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transition(
        OpenIssue $issue,
        OpenIssueStatus $target,
        User $actor,
        OpenIssueEventType $event,
        array $payload = []
    ): void {
        $current = $issue->status;
        $allowed = self::TRANSITIONS[$current->value];
        if (! in_array($target->value, $allowed, true)) {
            throw InvalidOpenIssueTransitionException::from($current, $target);
        }

        $issue->update(['status' => $target->value]);
        $this->record($issue, $event, $actor, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(OpenIssue $issue, OpenIssueEventType $event, User $actor, array $payload = []): void {
        OpenIssueEvent::query()->create([
            'open_issue_id' => $issue->id,
            'event' => $event->value,
            'actor_user_id' => $actor->id,
            'payload' => $payload !== [] ? $payload : null,
            'created_at' => Carbon::now(),
        ]);
    }

    private function parseSeverity(string $value): OpenIssueSeverity {
        $severity = OpenIssueSeverity::tryFrom($value);
        if (! $severity instanceof OpenIssueSeverity) {
            throw new InvalidArgumentException(sprintf('Unbekannte Severity „%s".', $value));
        }

        return $severity;
    }
}
