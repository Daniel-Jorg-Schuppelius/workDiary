<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Safety;

use App\Enums\Notification\NotificationEvent;
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource};
use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Models\{OpenIssue, SafetyEvent, User};
use App\Services\Notification\NotificationDispatcher;
use App\Services\OpenIssue\OpenIssueService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service für das Sicherheitsereignis-Register (Feature 013).
 *
 * Verantwortlich für Anlage (laufende event_no je Org), Statusmaschine und
 * den Abschluss (erfordert root_cause). Ein neues KRITISCHES Ereignis
 * (kind=accident ODER severity=critical) feuert synchron das Ereignis
 * safety.criticalEvent an die Leitungs-/Admin-Rollen (NotificationEvent).
 * Beim Schließen kann optional ein OpenIssue als Folgemaßnahme angelegt
 * werden.
 */
class SafetyEventService {
    use \App\Services\Concerns\AssignsSequentialNo;
    use \App\Services\Isms\Concerns\AssertsIsmsTransition;

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly OpenIssueService $openIssues,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $reporter, array $attributes): SafetyEvent {
        $event = DB::transaction(function () use ($reporter, $attributes): SafetyEvent {
            return SafetyEvent::query()->create([
                'organization_id' => $reporter->organization_id,
                'event_no' => $this->nextEventNo((int) $reporter->organization_id),
                'kind' => $attributes['kind'],
                'severity' => $attributes['severity'] ?? SafetyEventSeverity::Low->value,
                'occurred_at' => $attributes['occurred_at'] ?? Carbon::now(),
                'location' => $attributes['location'] ?? null,
                'subject_type' => $attributes['subject_type'] ?? null,
                'subject_id' => $attributes['subject_id'] ?? null,
                'reported_by_user_id' => $reporter->id,
                'affected_person' => $attributes['affected_person'] ?? null,
                'description' => $attributes['description'],
                'immediate_action' => $attributes['immediate_action'] ?? null,
                'status' => SafetyEventStatus::Reported->value,
            ]);
        });

        if ($this->isCritical($event)) {
            $this->notifyCritical($event);
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SafetyEvent $event, array $attributes): SafetyEvent {
        $wasCritical = $this->isCritical($event);

        $event->update([
            'kind' => $attributes['kind'] ?? $event->kind,
            'severity' => $attributes['severity'] ?? $event->severity,
            'occurred_at' => $attributes['occurred_at'] ?? $event->occurred_at,
            'location' => array_key_exists('location', $attributes) ? $attributes['location'] : $event->location,
            'affected_person' => array_key_exists('affected_person', $attributes) ? $attributes['affected_person'] : $event->affected_person,
            'description' => $attributes['description'] ?? $event->description,
            'immediate_action' => array_key_exists('immediate_action', $attributes) ? $attributes['immediate_action'] : $event->immediate_action,
            'root_cause' => array_key_exists('root_cause', $attributes) ? $attributes['root_cause'] : $event->root_cause,
        ]);

        // Erst durch ein Update kritisch geworden → einmalig (dedup) melden.
        if (! $wasCritical && $this->isCritical($event->refresh())) {
            $this->notifyCritical($event);
        }

        return $event;
    }

    /**
     * Statusübergang gemäß SafetyEventStatus::allowedTransitions(). Der
     * Wechsel nach closed erfordert eine Ursachenanalyse (root_cause);
     * setzt closed_at/closed_by, beim Wiedereröffnen werden sie geleert.
     */
    public function transition(SafetyEvent $event, SafetyEventStatus $target, User $actor): SafetyEvent {
        if ($event->status === $target) {
            return $event;
        }

        $this->assertIsmsTransition($event->status, $target, 'safety.error.invalid_transition');

        $changes = ['status' => $target->value];

        if ($target === SafetyEventStatus::Closed) {
            if (trim((string) $event->root_cause) === '') {
                throw ValidationException::withMessages([
                    'status' => (string) __('safety.error.close_requires_root_cause'),
                ]);
            }
            $changes['closed_at'] = Carbon::now();
            $changes['closed_by_user_id'] = $actor->id;
        }

        if ($event->status === SafetyEventStatus::Closed && $target !== SafetyEventStatus::Closed) {
            $changes['closed_at'] = null;
            $changes['closed_by_user_id'] = null;
        }

        $event->update($changes);

        return $event->refresh();
    }

    /**
     * Legt beim/nach dem Schließen optional einen OpenIssue als
     * Folgemaßnahme (Nacharbeit) zum Ereignis an.
     */
    public function createFollowUpIssue(SafetyEvent $event, User $actor, string $title, string $description = ''): OpenIssue {
        return $this->openIssues->create($event, $actor, [
            'source_type' => OpenIssueSource::Manual->value,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'severity' => $event->severity === SafetyEventSeverity::Critical
                ? OpenIssueSeverity::Critical->value
                : OpenIssueSeverity::High->value,
        ]);
    }

    public function delete(SafetyEvent $event): void {
        $event->delete();
    }

    /** Kritisch: Unfall ODER Schweregrad „kritisch". */
    private function isCritical(SafetyEvent $event): bool {
        return $event->kind === SafetyEventKind::Accident
            || $event->severity === SafetyEventSeverity::Critical;
    }

    private function notifyCritical(SafetyEvent $event): void {
        $this->dispatcher->notify(
            NotificationEvent::SafetyCriticalEvent,
            $event,
            null,
            [
                'title' => trim($event->displayNo() . ' — ' . $event->kind->label(), ' —'),
                'message' => (string) __('notification.message.safety_critical_event', [
                    'severity' => $event->severity->label(),
                    'location' => $event->location ?: '–',
                ]),
                'message_key' => 'notification.message.safety_critical_event',
                'message_params' => [
                    'severity' => ['key' => 'enums.safety.severity.' . $event->severity->value, 'fallback' => $event->severity->label()],
                    'location' => $event->location ?: '–',
                ],
                'url' => route('safety-events.show', $event),
            ],
            dedup: true,
        );
    }

    /** Nächste laufende Ereignis-Nummer der Organisation (innerhalb der Transaktion). */
    private function nextEventNo(int $organizationId): int {
        return $this->nextNo(SafetyEvent::class, 'event_no', 'organization_id', $organizationId);
    }
}
