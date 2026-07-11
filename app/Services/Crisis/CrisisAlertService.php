<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisAlertService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Enums\Notification\NotificationEvent;
use App\Models\Crisis\{CrisisCase, CrisisTeamAssignment};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\User;
use App\Notifications\GenericEventNotification;

/**
 * Alarmierung des Krisenstabs (Feature 070, MVP-213 / D7): direkte
 * Zustellung (In-App + Mail, bewusst OHNE Ruhezeit — crisis_alert),
 * Nachweis im NotificationDispatchLog, Quittierung je Stabsmitglied und
 * manuelle Wiederholung an die Stellvertretung bei ausbleibender
 * Quittierung.
 */
class CrisisAlertService {
    /** Alarmiert alle (noch nicht quittierten) Stabsmitglieder. */
    public function alert(CrisisCase $case, User $actor): int {
        $alerted = 0;
        foreach ($case->team()->with(['user', 'role'])->get() as $assignment) {
            if ($assignment->acknowledged_at !== null) {
                continue;
            }
            $user = $assignment->user;
            if ($user === null) {
                continue;
            }
            $this->deliver($case, $assignment, $user, 'initial');
            $assignment->update(['alerted_at' => now()]);
            $alerted++;
        }

        $case->audit('crisis.alerted', ['count' => $alerted, 'by' => $actor->id]);

        return $alerted;
    }

    /**
     * Eskalation (D7): nicht quittierte Alarme erneut zustellen — an das
     * Mitglied UND die hinterlegte Stellvertretung.
     */
    public function escalate(CrisisCase $case, User $actor): int {
        $escalated = 0;
        foreach ($case->team()->with(['user', 'deputy'])->get() as $assignment) {
            if ($assignment->acknowledged_at !== null || $assignment->alerted_at === null) {
                continue;
            }
            if ($assignment->user !== null) {
                $this->deliver($case, $assignment, $assignment->user, 'escalation');
            }
            if ($assignment->deputy !== null) {
                $this->deliver($case, $assignment, $assignment->deputy, 'escalation');
                $assignment->update(['deputy_alerted_at' => now()]);
            }
            $escalated++;
        }

        $case->audit('crisis.alert_escalated', ['count' => $escalated, 'by' => $actor->id]);

        return $escalated;
    }

    /** Quittierung durch das alarmierte Mitglied (oder die Stellvertretung). */
    public function acknowledge(CrisisTeamAssignment $assignment, User $actor): void {
        if ((int) $assignment->user_id !== (int) $actor->id && (int) ($assignment->deputy_user_id ?? 0) !== (int) $actor->id) {
            throw new \RuntimeException((string) __('Nur das alarmierte Stabsmitglied (oder die Stellvertretung) quittiert.'));
        }
        if ($assignment->acknowledged_at !== null) {
            return; // idempotent
        }

        $assignment->update(['acknowledged_at' => now()]);
        // Quittierungs-Nachweis auch am Dispatch-Log (D7-Vorleistung).
        NotificationDispatchLog::query()
            ->where('organization_id', $assignment->organization_id)
            ->where('event', NotificationEvent::CrisisAlert->value)
            ->where('subject_type', $assignment->getMorphClass())
            ->where('subject_id', $assignment->getKey())
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'acknowledged_by' => $actor->id]);
        $assignment->audit('crisis.alert_acknowledged', ['by' => $actor->id]);
    }

    private function deliver(CrisisCase $case, CrisisTeamAssignment $assignment, User $user, string $stage): void {
        // Krisenalarm überstimmt Ruhezeiten bewusst (D7): direkte Zustellung
        // In-App + Mail, unabhängig von Empfänger-Präferenzen.
        $user->notify(new GenericEventNotification(NotificationEvent::CrisisAlert, [
            'title' => (string) __('KRISENALARM: :title', ['title' => $case->title]),
            'message' => (string) __('Schweregrad :severity — bitte Alarm in der Krisenakte quittieren.', ['severity' => $case->severity]),
            'url' => route('crisis.show', $case),
        ], ['database', 'mail'], $stage));

        // Nachweis: unique je (Event, Assignment, Stufe) — wiederholte
        // Zustellungen derselben Stufe zählen den Empfängerzähler hoch.
        $log = NotificationDispatchLog::query()->firstOrCreate([
            'organization_id' => $case->organization_id,
            'event' => NotificationEvent::CrisisAlert->value,
            'subject_type' => $assignment->getMorphClass(),
            'subject_id' => $assignment->getKey(),
            'stage' => $stage,
        ], ['recipient_count' => 0]);
        $log->increment('recipient_count');
    }
}
