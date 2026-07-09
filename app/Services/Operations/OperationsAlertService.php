<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsAlertService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskStatus};
use App\Models\{OperationsTask, Organization};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Betriebs-Ereignis-Schiene (Feature 041, 041-P0): EINE Meldungsstelle
 * für alle Betriebsquellen (Backup, Ablauf, Update, Plugin, Scheduler,
 * Wartung) mit zwei Senken — Admin-Aufgabe (operations_tasks, idempotent
 * über dedupe_key mit Auto-Resolve) und Benachrichtigung über das
 * bestehende Notification-Framework (Regeln/Drosselung/Eskalation dort).
 *
 * Routing-Default: critical → Aufgabe + Benachrichtigung, warning →
 * Aufgabe + Benachrichtigung gemäß Regel, info → nur Benachrichtigung.
 */
class OperationsAlertService {
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function report(OperationsSignal $signal): ?OperationsTask {
        $now = CarbonImmutable::now();
        $organizationId = $signal->organizationId ?? $this->systemOrganizationId();
        if ($organizationId === null) {
            Log::warning('operations.no_organization_for_signal', ['dedupe_key' => $signal->dedupeKey]);

            return null;
        }

        $task = null;
        if ($signal->severity !== OperationsTaskSeverity::Info) {
            [$task, $shouldNotify] = $this->upsertTask($signal, $organizationId, $now);
        } else {
            $shouldNotify = true; // info = reine Meldung, keine Aufgabe
        }

        $notify = $signal->notify ?? $shouldNotify;
        if ($notify) {
            $this->publish($signal, $task, $organizationId);
        }

        return $task;
    }

    /** Ursache weggefallen → aktive Aufgabe automatisch schließen. */
    public function resolve(string $dedupeKey): void {
        OperationsTask::query()
            ->where('dedupe_key', $dedupeKey)
            ->whereNotIn('status', [OperationsTaskStatus::Done->value, OperationsTaskStatus::Resolved->value])
            ->get()
            ->each(function (OperationsTask $task): void {
                $task->update([
                    'status' => OperationsTaskStatus::Resolved,
                    'resolved_at' => CarbonImmutable::now(),
                ]);
            });
    }

    /**
     * @return array{0: OperationsTask, 1: bool} Aufgabe + „neu zu melden?"
     */
    private function upsertTask(OperationsSignal $signal, int $organizationId, CarbonImmutable $now): array {
        $existing = OperationsTask::query()
            ->where('dedupe_key', $signal->dedupeKey)
            ->first();

        if ($existing === null || in_array($existing->status, [OperationsTaskStatus::Done, OperationsTaskStatus::Resolved], true)) {
            // done/resolved = abgeschlossener Vorfall: dedupe_key wird für den
            // neuen Vorfall wiederverwendet (unique) — Zeile recyceln.
            $task = $existing ?? new OperationsTask;
            $task->fill([
                'organization_id' => $organizationId,
                'is_system' => $signal->organizationId === null,
                'type' => $signal->type,
                'severity' => $signal->severity,
                'status' => OperationsTaskStatus::Open,
                'dedupe_key' => $signal->dedupeKey,
                'title_key' => $signal->titleKey,
                'params' => $signal->params,
                'link_route' => $signal->linkRoute,
                'link_params' => $signal->linkParams,
                'assigned_role' => null,
                'assigned_user_id' => null,
                'snoozed_until' => null,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'resolved_at' => null,
                'acted_by_user_id' => null,
                'acted_at' => null,
                'note' => null,
            ]);
            $task->save();

            return [$task, true];
        }

        // Aktiver Vorfall: aktualisieren, nur bei Verschärfung oder
        // abgelaufenem Snooze erneut melden. Ignoriert bleibt ignoriert.
        $escalated = $signal->severity->rank() > $existing->severity->rank();
        $snoozeExpired = $existing->status === OperationsTaskStatus::Snoozed
            && $existing->snoozed_until !== null
            && $existing->snoozed_until->isPast();

        $existing->last_seen_at = $now;
        $existing->params = $signal->params;
        if ($escalated) {
            $existing->severity = $signal->severity;
        }
        if ($snoozeExpired || ($escalated && $existing->status === OperationsTaskStatus::Snoozed)) {
            $existing->status = OperationsTaskStatus::Open;
            $existing->snoozed_until = null;
        }
        $existing->save();

        $shouldNotify = $existing->status !== OperationsTaskStatus::Ignored
            && ($escalated || $snoozeExpired);

        return [$existing, $shouldNotify];
    }

    private function publish(OperationsSignal $signal, ?OperationsTask $task, int $organizationId): void {
        $event = $signal->type->notificationEvent();
        if ($event === null) {
            return;
        }

        $subject = $task;
        if ($subject === null) {
            // Info-Signale ohne Aufgabe: das Subjekt ist die Organisation
            // (liefert dem Dispatcher die org-Zuordnung).
            $subject = Organization::query()->find($organizationId);
            if ($subject === null) {
                return;
            }
        }

        $this->notifications->notify($event, $subject, null, [
            'title' => (string) __($signal->titleKey, $signal->params),
            'message' => $signal->message ?? '',
            'url' => $task?->url(),
        ]);
    }

    /**
     * Betreiber-Organisation für installationsweite Ereignisse:
     * Setting operations.system_org_id, sonst die erste Organisation.
     */
    private function systemOrganizationId(): ?int {
        $configured = Setting::get('operations.system_org_id');
        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        return Organization::query()->orderBy('id')->value('id');
    }
}
