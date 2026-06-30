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

use App\Models\Location\{CustomerGeofence, LocationPendingEntry, LocationVisit};
use App\Models\Scopes\OrganizationScope;
use App\Models\{TimeEntry, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Überführt geschlossene Aufenthalte in die Review-Inbox
 * ({@see LocationPendingEntry}) und – nach Bestätigung – in echte Zeitbuchungen.
 * Standortdaten werden nie automatisch final gebucht: zwischen Besuch und
 * {@see TimeEntry} steht immer eine bewusste Bestätigung durch den Nutzer.
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
            ->where('status', LocationVisit::STATUS_CLOSED)
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

        $project = $geofence->resolveProject();
        $enteredAt = $visit->entered_at;
        $leftAt = $visit->left_at ?? $enteredAt;

        DB::transaction(function () use ($visit, $geofence, $project, $enteredAt, $leftAt): void {
            LocationPendingEntry::create([
                'organization_id' => $visit->organization_id,
                'user_id' => $visit->user_id,
                'location_visit_id' => $visit->id,
                'customer_id' => $geofence->customer_id,
                'project_id' => $project->id,
                'suggested_date' => $enteredAt->copy()->startOfDay(),
                'started_at' => $enteredAt,
                'ended_at' => $leftAt,
                'minutes' => (int) ($visit->duration_min ?? $enteredAt->diffInMinutes($leftAt)),
                'description' => $geofence->label,
                'status' => LocationPendingEntry::STATUS_OPEN,
            ]);

            $visit->forceFill(['materialized' => true])->save();
        });

        return true;
    }

    /**
     * Bestätigt einen Vorschlag: erzeugt die Zeitbuchung und schließt die Inbox-
     * Zeile. Optionale Overrides (project_id, description, started_at, ended_at)
     * erlauben Korrekturen vor dem Buchen.
     *
     * @param array<string, mixed> $overrides
     */
    public function confirm(LocationPendingEntry $entry, User $resolver, array $overrides = []): TimeEntry {
        if ($entry->status !== LocationPendingEntry::STATUS_OPEN) {
            throw new \RuntimeException('Vorschlag ist bereits aufgelöst.');
        }

        $startedAt = isset($overrides['started_at']) ? Carbon::parse($overrides['started_at']) : $entry->started_at;
        $endedAt = isset($overrides['ended_at']) ? Carbon::parse($overrides['ended_at']) : $entry->ended_at;

        return DB::transaction(function () use ($entry, $resolver, $overrides, $startedAt, $endedAt): TimeEntry {
            $timeEntry = TimeEntry::create([
                'organization_id' => $entry->organization_id,
                'project_id' => $overrides['project_id'] ?? $entry->project_id,
                'user_id' => $entry->user_id,
                'date' => $startedAt->copy()->startOfDay(),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'description' => $overrides['description'] ?? $entry->description,
            ]);

            $entry->forceFill([
                'status' => LocationPendingEntry::STATUS_IMPORTED,
                'time_entry_id' => $timeEntry->id,
                'resolved_by' => $resolver->id,
                'resolved_at' => Carbon::now(),
            ])->save();

            return $timeEntry;
        });
    }

    public function dismiss(LocationPendingEntry $entry, User $resolver): void {
        if ($entry->status !== LocationPendingEntry::STATUS_OPEN) {
            return;
        }

        $entry->forceFill([
            'status' => LocationPendingEntry::STATUS_DISMISSED,
            'resolved_by' => $resolver->id,
            'resolved_at' => Carbon::now(),
        ])->save();
    }
}
