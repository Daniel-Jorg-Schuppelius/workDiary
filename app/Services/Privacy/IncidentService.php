<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\Privacy\{IncidentStatus, IncidentType};
use App\Models\Organization;
use App\Models\Privacy\{Incident, IncidentEvent, Measure};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workflow der Datenschutzvorfaelle (Art. 33/34): Anlegen mit per-Fall-Krypto und
 * 72-h-Meldefrist, Risikobewertung, Meldeentscheidung, Meldung, Abschluss sowie
 * Massnahmenverfolgung. Jeder Schritt schreibt ein Ereignis in die Hash-Kette.
 */
class IncidentService {
    public function open(
        Organization $organization,
        IncidentType $type,
        string $summary,
        ?string $affected = null,
        ?Carbon $occurredAt = null,
        ?User $actor = null,
    ): Incident {
        return DB::transaction(function () use ($organization, $type, $summary, $affected, $occurredAt, $actor): Incident {
            $now = Carbon::now();

            $incident = new Incident;
            $incident->organization_id = $organization->id;
            $incident->incident_number = $this->nextNumber($organization, $now);
            $incident->type = $type;
            $incident->status = IncidentStatus::Detected;
            $incident->occurred_at = $occurredAt;
            $incident->discovered_at = $now;
            $incident->reported_internally_at = $now;
            $incident->authority_deadline_at = $now->copy()->addHours(72); // Art. 33 Abs. 1
            $incident->setAttribute('created_by', $actor?->id);
            $incident->initializeDek();
            $incident->summary_ciphertext = $summary;
            if ($affected !== null) {
                $incident->affected_ciphertext = $affected;
            }
            $incident->save();

            $this->event($incident, 'opened', $actor, ['type' => $type->value]);

            return $incident;
        });
    }

    public function assess(Incident $incident, string $riskLevel, ?string $measures, ?User $actor = null): Incident {
        $incident->risk_level = $riskLevel;
        if ($measures !== null) {
            $incident->measures_ciphertext = $measures;
        }
        $incident->forceFill(['status' => IncidentStatus::Assessing])->save();
        $this->event($incident, 'assessed', $actor, ['risk_level' => $riskLevel]);

        return $incident;
    }

    public function decideNotification(Incident $incident, bool $authority, bool $subjects, ?User $actor = null): Incident {
        $incident->forceFill([
            'notify_authority' => $authority,
            'notify_subjects' => $subjects,
        ])->save();
        $this->event($incident, 'notification_decided', $actor, ['authority' => $authority, 'subjects' => $subjects]);

        return $incident;
    }

    public function markReported(Incident $incident, bool $authority, bool $subjects, ?User $actor = null): Incident {
        $now = Carbon::now();
        $incident->forceFill([
            'status' => IncidentStatus::Reported,
            'authority_notified_at' => $authority ? $now : $incident->getAttribute('authority_notified_at'),
            'subjects_notified_at' => $subjects ? $now : $incident->getAttribute('subjects_notified_at'),
        ])->save();
        $this->event($incident, 'reported', $actor, ['authority' => $authority, 'subjects' => $subjects]);

        return $incident;
    }

    public function close(Incident $incident, ?string $lessons, ?User $actor = null): Incident {
        if ($lessons !== null) {
            $incident->lessons_ciphertext = $lessons;
        }
        $incident->forceFill(['status' => IncidentStatus::Closed, 'closed_at' => Carbon::now()])->save();
        $this->event($incident, 'closed', $actor);

        return $incident;
    }

    public function addMeasure(Incident $incident, string $title, ?string $description, ?Carbon $dueAt, ?User $actor = null): Measure {
        $measure = Measure::create([
            'organization_id' => $incident->organization_id,
            'incident_id' => $incident->id,
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt?->toDateString(),
            'status' => 'open',
            'created_by' => $actor?->id,
        ]);
        $this->event($incident, 'measure_added', $actor, ['measure_id' => $measure->id]);

        return $measure;
    }

    public function completeMeasure(Measure $measure, ?User $actor = null): Measure {
        $measure->forceFill(['status' => 'done', 'completed_at' => Carbon::now()])->save();
        $incident = $measure->incident;
        if ($incident !== null) {
            $this->event($incident, 'measure_completed', $actor, ['measure_id' => $measure->id]);
        }

        return $measure;
    }

    /** @param array<string, mixed> $metadata */
    private function event(Incident $incident, string $event, ?User $actor, array $metadata = []): void {
        IncidentEvent::create([
            'organization_id' => $incident->organization_id,
            'incident_id' => $incident->id,
            'actor_type' => $actor instanceof User ? 'staff' : 'system',
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private function nextNumber(Organization $organization, Carbon $now): string {
        $count = Incident::query()
            ->where('organization_id', $organization->id)
            ->whereYear('discovered_at', $now->year)
            ->count();

        return sprintf('DSV-%d-%04d', $now->year, $count + 1);
    }
}
