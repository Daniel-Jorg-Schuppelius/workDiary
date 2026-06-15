<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityIncidentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{IncidentSeverity, SecurityIncidentStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\Isms\{IsmsControl, IsmsRisk, IsmsSecurityIncident};
use App\Models\User;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service ISMS-Sicherheitsvorfälle (Feature 044, MVP 2).
 *
 * Geschäftsregeln:
 * - incident_no: laufende Nummer je Organisation (Vergabe in der Transaktion,
 *   Unique-Index isms_si_org_no_uq sichert Kollisionen ab).
 * - Statusübergänge ausschließlich über transition() entlang
 *   SecurityIncidentStatus::allowedTransitions().
 * - Abschluss (closed) erfordert root_cause UND lessons_learned (044:
 *   Ursachenanalyse + Lessons Learned). Beim Wechsel nach contained wird
 *   contained_at, beim Wechsel nach closed closed_at gesetzt (sofern leer).
 * - personal_data_affected ist NUR ein Hinweis auf eine separate
 *   Datenschutzmeldung — die Fallakten werden NICHT zusammengelegt; die lose
 *   Kopplung läuft über das Freitextfeld privacy_incident_ref (Sqid/ID eines
 *   Privacy\Incident, kein FK).
 * - Rückführung in Risiken/Maßnahmen über die Pivots isms_incident_risk /
 *   isms_incident_control (org-gescopte Auflösung wie im RiskService).
 *
 * Audit läuft über den Auditable-Trait (created/updated/deleted) plus gezielte
 * audit()-Events für Statusübergänge.
 */
class SecurityIncidentService {
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Legt einen Vorfall an (Status default reported). Ein neuer KRITISCHER
     * Vorfall feuert synchron das Ereignis isms.incidentCritical an die
     * Leitungs-/Admin-Rollen (NotificationEvent).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsSecurityIncident {
        $incident = DB::transaction(function () use ($creator, $attributes): IsmsSecurityIncident {
            $incident = IsmsSecurityIncident::query()->create([
                'organization_id' => $creator->organization_id,
                'isms_scope_id' => $attributes['isms_scope_id'] ?? null,
                'incident_no' => $this->nextIncidentNo((int) $creator->organization_id),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'category' => $attributes['category'],
                'severity' => $attributes['severity'],
                'status' => SecurityIncidentStatus::Reported->value,
                'detected_at' => $attributes['detected_at'] ?? Carbon::now(),
                'occurred_at' => $attributes['occurred_at'] ?? null,
                'reporter_user_id' => $attributes['reporter_user_id'] ?? $creator->id,
                'owner_user_id' => $attributes['owner_user_id'] ?? null,
                'impact' => $attributes['impact'] ?? null,
                'root_cause' => $attributes['root_cause'] ?? null,
                'lessons_learned' => $attributes['lessons_learned'] ?? null,
                'personal_data_affected' => (bool) ($attributes['personal_data_affected'] ?? false),
                'privacy_incident_ref' => $attributes['privacy_incident_ref'] ?? null,
            ]);

            if (array_key_exists('risk_ids', $attributes)) {
                $this->syncRisks($incident, $this->normalizeIds($attributes['risk_ids']));
            }
            if (array_key_exists('control_ids', $attributes)) {
                $this->syncControls($incident, $this->normalizeIds($attributes['control_ids']));
            }

            return $incident;
        });

        if ($incident->severity === IncidentSeverity::Critical) {
            $this->dispatcher->notify(
                NotificationEvent::IsmsIncidentCritical,
                $incident,
                null,
                [
                    'title' => trim($incident->displayNo() . ' — ' . $incident->title, ' —'),
                    'message' => (string) __('notification.message.incident_critical'),
                    'url' => route('isms.incidents.index'),
                ],
                dedup: true,
            );
        }

        return $incident;
    }

    /**
     * Aktualisiert Stammdaten; der Status bleibt unangetastet — Übergänge
     * laufen über transition().
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsSecurityIncident $incident, User $actor, array $attributes): IsmsSecurityIncident {
        return DB::transaction(function () use ($incident, $attributes): IsmsSecurityIncident {
            $incident->update([
                'isms_scope_id' => array_key_exists('isms_scope_id', $attributes) ? $attributes['isms_scope_id'] : $incident->isms_scope_id,
                'title' => $attributes['title'] ?? $incident->title,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $incident->description,
                'category' => $attributes['category'] ?? $incident->category,
                'severity' => $attributes['severity'] ?? $incident->severity,
                'detected_at' => array_key_exists('detected_at', $attributes) ? $attributes['detected_at'] : $incident->detected_at,
                'occurred_at' => array_key_exists('occurred_at', $attributes) ? $attributes['occurred_at'] : $incident->occurred_at,
                'owner_user_id' => array_key_exists('owner_user_id', $attributes) ? $attributes['owner_user_id'] : $incident->owner_user_id,
                'impact' => array_key_exists('impact', $attributes) ? $attributes['impact'] : $incident->impact,
                'root_cause' => array_key_exists('root_cause', $attributes) ? $attributes['root_cause'] : $incident->root_cause,
                'lessons_learned' => array_key_exists('lessons_learned', $attributes) ? $attributes['lessons_learned'] : $incident->lessons_learned,
                'personal_data_affected' => array_key_exists('personal_data_affected', $attributes) ? (bool) $attributes['personal_data_affected'] : $incident->personal_data_affected,
                'privacy_incident_ref' => array_key_exists('privacy_incident_ref', $attributes) ? $attributes['privacy_incident_ref'] : $incident->privacy_incident_ref,
            ]);

            if (array_key_exists('risk_ids', $attributes)) {
                $this->syncRisks($incident, $this->normalizeIds($attributes['risk_ids']));
            }
            if (array_key_exists('control_ids', $attributes)) {
                $this->syncControls($incident, $this->normalizeIds($attributes['control_ids']));
            }

            return $incident;
        });
    }

    /**
     * Statusübergang entlang der State-Machine
     * ({@see SecurityIncidentStatus::allowedTransitions()}).
     *
     * Abschlussregel (044): der Wechsel nach closed erfordert root_cause UND
     * lessons_learned. Setzt contained_at/closed_at bei den entsprechenden
     * Übergängen (sofern noch leer).
     *
     * @throws ValidationException bei unzulässigem Übergang / fehlenden Pflichtfeldern
     */
    public function transition(IsmsSecurityIncident $incident, SecurityIncidentStatus $target, User $actor): IsmsSecurityIncident {
        if ($incident->status === $target) {
            return $incident;
        }

        if (! in_array($target, $incident->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('isms.error.invalid_transition', [
                    'from' => $incident->status->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        if ($target === SecurityIncidentStatus::Closed) {
            if (trim((string) $incident->root_cause) === '' || trim((string) $incident->lessons_learned) === '') {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.incident_close_requires_root_cause'),
                ]);
            }
        }

        return DB::transaction(function () use ($incident, $target, $actor): IsmsSecurityIncident {
            $from = $incident->status;

            $changes = ['status' => $target->value];
            if ($target === SecurityIncidentStatus::Contained && $incident->contained_at === null) {
                $changes['contained_at'] = Carbon::now();
            }
            if ($target === SecurityIncidentStatus::Closed && $incident->closed_at === null) {
                $changes['closed_at'] = Carbon::now();
            }

            $incident->update($changes);
            $incident->audit('isms.security_incident.transitioned', [
                'actor_user_id' => $actor->id,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $incident;
        });
    }

    /** Soft-Delete (Policy: isms.manage bzw. Admin). */
    public function delete(IsmsSecurityIncident $incident, User $actor): void {
        DB::transaction(function () use ($incident, $actor): void {
            $incident->audit('isms.security_incident.deleted', ['actor_user_id' => $actor->id]);
            $incident->risks()->detach();
            $incident->controls()->detach();
            $incident->delete();
        });
    }

    /**
     * Synchronisiert die Risiko-Verknüpfung. Die IDs werden über die
     * org-gescopte Risk-Query aufgelöst — fremde Organisationen können dadurch
     * nicht verknüpft werden (Pivot trägt bewusst keine organization_id).
     *
     * @param  list<int|string>  $riskIds
     */
    public function syncRisks(IsmsSecurityIncident $incident, array $riskIds): void {
        $ids = IsmsRisk::query()
            ->whereIn('id', array_map(intval(...), $riskIds))
            ->pluck('id')
            ->all();

        $incident->risks()->sync($ids);
    }

    /**
     * Synchronisiert die Maßnahmen-Verknüpfung (org-gescopt).
     *
     * @param  list<int|string>  $controlIds
     */
    public function syncControls(IsmsSecurityIncident $incident, array $controlIds): void {
        $ids = IsmsControl::query()
            ->whereIn('id', array_map(intval(...), $controlIds))
            ->pluck('id')
            ->all();

        $incident->controls()->sync($ids);
    }

    /**
     * Normalisiert rohe Request-Werte zu einer ID-Liste (nur int/string).
     *
     * @return list<int|string>
     */
    private function normalizeIds(mixed $value): array {
        return array_values(array_filter(
            (array) $value,
            static fn(mixed $id): bool => is_int($id) || is_string($id),
        ));
    }

    /** Nächste laufende Vorfall-Nummer der Organisation (innerhalb der Transaktion). */
    private function nextIncidentNo(int $organizationId): int {
        $max = IsmsSecurityIncident::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->lockForUpdate()
            ->max('incident_no');

        return ((int) $max) + 1;
    }
}
