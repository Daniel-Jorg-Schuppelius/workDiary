<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VisitBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Location;

use App\Models\Location\{CustomerGeofence, LocationPoint, LocationVisit};
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wandelt die rohe GPS-Spur eines Nutzers in Aufenthalte ({@see LocationVisit})
 * um. Streaming-fähig: ein noch offener Besuch wird über mehrere Batches hinweg
 * fortgeschrieben. Berücksichtigt:
 *  - Verweildauer (geofence.min_dwell_minutes) – Durchfahrten werden verworfen,
 *  - Lücken-Merge (geofence.gap_merge_minutes) – kurze Aussetzer beenden den
 *    Besuch nicht,
 *  - Genauigkeitsfilter – grob lokalisierte Punkte werden ignoriert.
 *
 * Erwartet eine gebundene Organisation NICHT; alle Abfragen sind explizit auf
 * Organisation und Nutzer gescoped, damit der Job tenant-sicher läuft.
 */
class VisitBuilder {
    public const MAX_ACCURACY_M = 200;

    public function __construct(private readonly GeofenceMatcher $matcher) {}

    /**
     * Verarbeitet alle noch nicht verarbeiteten Punkte des Nutzers und gibt die
     * Anzahl verarbeiteter Punkte zurück.
     */
    public function rebuildForUser(User $user): int {
        $orgId = (int) $user->organization_id;

        $geofences = $this->activeGeofences($orgId);
        if ($geofences->isEmpty()) {
            // Ohne Geofences gibt es nichts zuzuordnen – Punkte trotzdem als
            // verarbeitet markieren, damit sie nicht erneut betrachtet werden.
            return $this->markRemainingProcessed($orgId, (int) $user->id);
        }

        $points = LocationPoint::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('user_id', $user->id)
            ->whereNull('processed_at')
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        if ($points->isEmpty()) {
            return 0;
        }

        $open = $this->currentOpenVisit($orgId, (int) $user->id);
        $now = Carbon::now();

        DB::transaction(function () use ($points, $geofences, &$open, $orgId, $user, $now): void {
            foreach ($points as $point) {
                if ($point->accuracy_m !== null && $point->accuracy_m > self::MAX_ACCURACY_M) {
                    continue; // grob lokalisiert: weder öffnen noch schließen
                }

                $match = $this->matcher->match((float) $point->lat, (float) $point->lng, $geofences);
                $open = $this->advance($open, $match, $point, $orgId, (int) $user->id);
            }

            // Punkte als verarbeitet markieren.
            LocationPoint::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->whereIn('id', $points->pluck('id'))
                ->update(['processed_at' => $now]);
        });

        return $points->count();
    }

    /**
     * Schreibt den Zustandsautomaten um einen Punkt weiter und liefert den
     * (ggf. neuen) offenen Besuch zurück.
     */
    private function advance(?LocationVisit $open, ?CustomerGeofence $match, LocationPoint $point, int $orgId, int $userId): ?LocationVisit {
        $at = $point->recorded_at;

        if ($open === null) {
            return $match !== null ? $this->openVisit($match, $point, $orgId, $userId) : null;
        }

        if ($match !== null && $match->id === $open->customer_geofence_id) {
            // Weiterhin im selben Geofence: Besuch verlängern.
            $open->left_at = $at;
            $open->sample_count++;
            $open->save();

            return $open;
        }

        if ($match !== null) {
            // Direkter Wechsel in einen anderen Geofence.
            $this->closeVisit($open);

            return $this->openVisit($match, $point, $orgId, $userId);
        }

        // Kein Match: kurzen Aussetzer tolerieren, sonst Besuch beenden.
        $gapMinutes = $open->left_at ? $open->left_at->diffInMinutes($at) : 0;
        $gapMerge = $open->geofence->gap_merge_minutes ?? 0;

        if ($gapMinutes > $gapMerge) {
            $this->closeVisit($open);

            return null;
        }

        return $open; // innerhalb der Toleranz – Besuch bleibt offen
    }

    private function openVisit(CustomerGeofence $geofence, LocationPoint $point, int $orgId, int $userId): LocationVisit {
        $visit = new LocationVisit([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'customer_geofence_id' => $geofence->id,
            'entered_at' => $point->recorded_at,
            'left_at' => $point->recorded_at,
            'sample_count' => 1,
            'status' => LocationVisit::STATUS_OPEN,
            'materialized' => false,
        ]);
        $visit->save();
        $visit->setRelation('geofence', $geofence);

        return $visit;
    }

    /**
     * Schließt einen Besuch. Aufenthalte unter der Mindest-Verweildauer des
     * Geofence gelten als Durchfahrt und werden verworfen.
     */
    private function closeVisit(LocationVisit $visit): void {
        $enteredAt = $visit->entered_at;
        $leftAt = $visit->left_at ?? $enteredAt;
        $duration = (int) $enteredAt->diffInMinutes($leftAt);

        $minDwell = $visit->geofence->min_dwell_minutes ?? 0;
        if ($duration < $minDwell) {
            $visit->delete();

            return;
        }

        $visit->left_at = $leftAt;
        $visit->duration_min = $duration;
        $visit->status = LocationVisit::STATUS_CLOSED;
        $visit->save();
    }

    /** @return Collection<int, CustomerGeofence> */
    private function activeGeofences(int $orgId): Collection {
        return CustomerGeofence::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();
    }

    private function currentOpenVisit(int $orgId, int $userId): ?LocationVisit {
        $visit = LocationVisit::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('status', LocationVisit::STATUS_OPEN)
            ->latest('entered_at')
            ->first();

        if ($visit !== null) {
            $visit->load('geofence');
        }

        return $visit;
    }

    private function markRemainingProcessed(int $orgId, int $userId): int {
        return LocationPoint::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->whereNull('processed_at')
            ->update(['processed_at' => Carbon::now()]);
    }
}
