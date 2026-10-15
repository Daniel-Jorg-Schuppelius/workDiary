<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VisitMaterializer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Location;

use App\Enums\Location\{LocationPendingEntryStatus, LocationVisitStatus};
use App\Models\Diary\{DiaryEntry, Tour, TourStop};
use App\Models\Location\{CustomerGeofence, LocationPendingEntry, LocationVisit};
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Models\Time\TimeEntry;
use App\Support\Query\DateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Überführt geschlossene Aufenthalte in die Review-Inbox
 * ({@see LocationPendingEntry}) und – nach Bestätigung – in echte Zeitbuchungen.
 * Standortdaten werden nie automatisch final gebucht: zwischen Besuch und
 * {@see TimeEntry} steht immer eine bewusste Bestätigung durch den Nutzer.
 * Seit MVP-1182 kennt der Vorschlag den Auftrag (Tourstopp oder Einsatz des
 * Tages beim Kunden); die Bestätigung schreibt das Stopp-Ist.
 */
class VisitMaterializer {
    /**
     * Erzeugt für jeden geschlossenen, noch nicht materialisierten Besuch des
     * Nutzers einen Inbox-Vorschlag. Gibt die Anzahl erzeugter Vorschläge zurück.
     */
    public function materializeForUser(User $user): int {
        $visits = LocationVisit::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('status', LocationVisitStatus::Closed)
            ->where('materialized', false)
            ->with('geofence.customer')
            ->orderBy('entered_at')
            ->get();

        $created = 0;
        foreach ($visits as $visit) {
            if ($this->materializeVisit($visit)) {
                $created++;
            }
        }

        return $created;
    }

    private function materializeVisit(LocationVisit $visit): bool {
        $geofence = $visit->geofence;
        if (! $geofence instanceof CustomerGeofence) {
            $visit->forceFill(['materialized' => true])->save();

            return false;
        }

        $enteredAt = $visit->entered_at;
        $leftAt = $visit->left_at ?? $enteredAt;
        $entry = $this->matchOrder($visit, $geofence);
        // Standardprojekt erst beim Bestätigen anlegen (MVP-1186) — der Job legt nichts an.
        $project = $entry->project ?? $this->existingProject($geofence);
        $customerId = $geofence->customer_id ?? $entry?->customer_id;

        DB::transaction(function () use ($visit, $geofence, $project, $entry, $customerId, $enteredAt, $leftAt): void {
            LocationPendingEntry::create([
                'organization_id' => $visit->organization_id,
                'user_id' => $visit->user_id,
                'location_visit_id' => $visit->id,
                'customer_id' => $customerId,
                'project_id' => $project?->id,
                'diary_entry_id' => $entry?->id,
                'suggested_date' => $enteredAt->copy()->startOfDay(),
                'started_at' => $enteredAt,
                'ended_at' => $leftAt,
                'minutes' => (int) ($visit->duration_min ?? $enteredAt->diffInMinutes($leftAt)),
                'description' => $entry->title ?? $geofence->label,
                'status' => LocationPendingEntryStatus::Open,
            ]);

            $visit->forceFill(['materialized' => true])->save();
        });

        return true;
    }

    /**
     * Auftrag zum Besuch (MVP-1182): Tourzone → ihr Auftrag; sonst der
     * Tourstopp des Nutzers am Besuchstag beim Kunden der Zone; sonst ein dem
     * Nutzer zugewiesener Einsatz des Tages beim Kunden.
     */
    private function matchOrder(LocationVisit $visit, CustomerGeofence $geofence): ?DiaryEntry {
        if ($geofence->diary_entry_id !== null) {
            return DiaryEntry::query()->withoutGlobalScope(OrganizationScope::class)->find($geofence->diary_entry_id);
        }
        if ($geofence->customer_id === null) {
            return null;
        }
        $day = $visit->entered_at->toDateString();
        $tourIds = Tour::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $visit->organization_id)
            ->where('user_id', $visit->user_id)
            ->whereBetween('tour_date', DateRange::days($day, $day))
            ->pluck('id');
        if ($tourIds->isNotEmpty()) {
            $stop = DiaryEntry::query()->withoutGlobalScope(OrganizationScope::class)
                ->whereIn('tour_id', $tourIds)
                ->where('customer_id', $geofence->customer_id)
                ->orderByRaw('tour_position IS NULL')
                ->orderBy('tour_position')
                ->first();
            if ($stop instanceof DiaryEntry) {
                return $stop;
            }
        }

        return DiaryEntry::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $visit->organization_id)
            ->where('assigned_user_id', $visit->user_id)
            ->where('customer_id', $geofence->customer_id)
            ->whereBetween('scheduled_for', DateRange::days($day, $day))
            ->orderBy('id')
            ->first();
    }

    /** Projekt der Zone, ihres Auftrags oder das vorhandene Standardprojekt des Kunden — ohne anzulegen. */
    private function existingProject(CustomerGeofence $geofence): ?\App\Models\Project\Project {
        return $geofence->project ?? $geofence->diaryEntry->project ?? $geofence->customer?->defaultProject();
    }

    private function resolveProjectOrNull(CustomerGeofence $geofence): ?\App\Models\Project\Project {
        try {
            return $geofence->resolveProject();
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Bestätigt einen Vorschlag: erzeugt die Zeitbuchung und schließt die Inbox-
     * Zeile. Optionale Overrides (project_id, diary_entry_id, description,
     * started_at, ended_at) erlauben Korrekturen vor dem Buchen. Gehört der
     * Auftrag zu einer Tour, bekommt der Stopp sein Ist (Quelle Standort).
     *
     * @param array<string, mixed> $overrides
     */
    public function confirm(LocationPendingEntry $entry, User $resolver, array $overrides = []): TimeEntry {
        if (! $entry->status->canTransitionTo(LocationPendingEntryStatus::Imported)) {
            throw new \RuntimeException('Vorschlag ist bereits aufgelöst.');
        }

        $startedAt = isset($overrides['started_at']) ? Carbon::parse($overrides['started_at']) : $entry->started_at;
        $endedAt = isset($overrides['ended_at']) ? Carbon::parse($overrides['ended_at']) : $entry->ended_at;
        $diaryEntryId = array_key_exists('diary_entry_id', $overrides) ? $overrides['diary_entry_id'] : $entry->diary_entry_id;
        $projectId = $overrides['project_id'] ?? $entry->project_id;
        if ($projectId === null) {
            // Standardprojekt des Kunden entsteht erst hier, beim Bestätigen (MVP-1186).
            $geofence = $entry->visit?->geofence;
            $projectId = $geofence instanceof CustomerGeofence ? $this->resolveProjectOrNull($geofence)?->id : null;
        }
        return DB::transaction(function () use ($entry, $resolver, $overrides, $startedAt, $endedAt, $diaryEntryId, $projectId): TimeEntry {
            $timeEntry = TimeEntry::create([
                'organization_id' => $entry->organization_id,
                'project_id' => $projectId,
                'user_id' => $entry->user_id,
                'diary_entry_id' => $diaryEntryId,
                'date' => $startedAt->copy()->startOfDay(),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'description' => $overrides['description'] ?? $entry->description,
            ]);

            if ($diaryEntryId !== null) {
                $this->recordStopActuals((int) $diaryEntryId, $startedAt, $endedAt);
            }

            $entry->forceFill([
                'status' => LocationPendingEntryStatus::Imported,
                'diary_entry_id' => $diaryEntryId,
                'project_id' => $projectId ?? $entry->project_id,
                'time_entry_id' => $timeEntry->id,
                'resolved_by' => $resolver->id,
                'resolved_at' => Carbon::now(),
            ])->save();

            return $timeEntry;
        });
    }

    /** Stopp-Ist aus dem Besuch (MVP-1182); ein manuell gesetztes Ist bleibt stehen. */
    private function recordStopActuals(int $diaryEntryId, Carbon $arrivedAt, Carbon $departedAt): void {
        $stop = TourStop::query()->where('diary_entry_id', $diaryEntryId)->whereNull('arrived_at')->first();
        if (! $stop instanceof TourStop) {
            return;
        }
        $stop->forceFill([
            'arrived_at' => $arrivedAt,
            'departed_at' => $departedAt,
            'actual_source' => TourStop::SOURCE_GEOFENCE,
        ])->save();
    }

    public function dismiss(LocationPendingEntry $entry, User $resolver): void {
        if (! $entry->status->canTransitionTo(LocationPendingEntryStatus::Dismissed)) {
            return;
        }

        $entry->forceFill([
            'status' => LocationPendingEntryStatus::Dismissed,
            'resolved_by' => $resolver->id,
            'resolved_at' => Carbon::now(),
        ])->save();
    }
}
