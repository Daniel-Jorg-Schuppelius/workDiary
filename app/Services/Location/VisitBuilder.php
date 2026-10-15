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

use App\Enums\Location\{LocationPendingEntryStatus, LocationPendingTripStatus, LocationVisitStatus};
use App\Models\Location\{CustomerGeofence, LocationPendingTrip, LocationPoint, LocationVisit};
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Support\Geo\GeofenceMatcher;
use App\Support\{OrganizationContext, Tz};
use Illuminate\Support\{Carbon, Collection};
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

    public function __construct(
        private readonly GeofenceMatcher $matcher,
        private readonly VisitMaterializer $materializer,
        private readonly TripBuilder $trips,
    ) {}

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

        $this->rewindForLateArrivals($orgId, (int) $user->id);

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
        $organization = $user->organization;
        $timezone = $organization instanceof Organization ? Tz::ofOrganization($organization) : Tz::current();

        DB::transaction(function () use ($points, $geofences, &$open, $orgId, $user, $now, $timezone): void {
            foreach ($points as $point) {
                if ($point->accuracy_m !== null && $point->accuracy_m > self::MAX_ACCURACY_M) {
                    continue; // grob lokalisiert: weder öffnen noch schließen
                }

                // Temporäre Tourzonen (MVP-1181) gelten nur an ihrem Tag (Ortszeit der Organisation).
                $recordedLocal = $point->recorded_at->copy()->setTimezone($timezone);
                $candidates = $geofences->filter(static fn(CustomerGeofence $zone): bool => $zone->coversDate($recordedLocal));
                $match = $this->matcher->match((float) $point->lat, (float) $point->lng, $candidates, $point->accuracy_m);
                // Hysterese (MVP-1186): Eintritt streng (Abstand + Genauigkeit), Austritt erst,
                // wenn der Punkt mit seiner Genauigkeit die Zone sicher verlassen hat.
                if ($match === null && $open !== null && $open->geofence !== null
                    && $this->matcher->stillInside((float) $point->lat, (float) $point->lng, $open->geofence, $point->accuracy_m)) {
                    $match = $open->geofence;
                }
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
            'status' => LocationVisitStatus::Open,
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
        $visit->status = LocationVisitStatus::Closed;
        $visit->save();

        // Fahrtvorschlag vom vorigen Besuch zu diesem (MVP-1185).
        $this->trips->buildAfter($visit);
    }

    /**
     * Nachzügler (MVP-1186): Liefert ein Offline-Puffer Punkte, die älter sind als
     * schon gebildete Besuche, werden diese Besuche samt offenem Vorschlag
     * verworfen und ihre Punkte neu verarbeitet. Besuche mit bestätigtem oder
     * verworfenem Vorschlag bleiben; Punkte aus deren Zeitraum gelten als erledigt.
     */
    private function rewindForLateArrivals(int $orgId, int $userId): void {
        $earliest = LocationPoint::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->whereNull('processed_at')
            ->min('recorded_at');
        if ($earliest === null) {
            return;
        }
        $final = [LocationPendingEntryStatus::Imported->value, LocationPendingEntryStatus::Dismissed->value];
        $visits = LocationVisit::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('left_at', '>=', $earliest)
            ->with('pendingEntry')
            ->get();
        if ($visits->isEmpty()) {
            return;
        }
        $isFinal = static fn(LocationVisit $v): bool => $v->pendingEntry !== null && in_array($v->pendingEntry->status->value, $final, true);
        $settled = $visits->filter($isFinal);
        $affected = $visits->reject($isFinal);

        DB::transaction(function () use ($settled, $affected, $orgId, $userId): void {
            $settledUntil = $settled->max('left_at');
            if ($settledUntil !== null) {
                // Nachzügler im Zeitraum eines bestätigten Besuchs ändern nichts mehr.
                LocationPoint::query()
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $orgId)
                    ->where('user_id', $userId)
                    ->whereNull('processed_at')
                    ->where('recorded_at', '<=', $settledUntil)
                    ->update(['processed_at' => Carbon::now()]);
            }
            if ($affected->isEmpty()) {
                return;
            }
            // Offene Fahrtvorschläge an diesen Besuchen entstehen neu (MVP-1185).
            LocationPendingTrip::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('status', LocationPendingTripStatus::Open)
                ->where(fn($q) => $q->whereIn('from_visit_id', $affected->modelKeys())->orWhereIn('to_visit_id', $affected->modelKeys()))
                ->delete();
            $from = $affected->min('entered_at');
            foreach ($affected as $visit) {
                $visit->pendingEntry()->delete();
                $visit->delete();
            }
            LocationPoint::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $orgId)
                ->where('user_id', $userId)
                ->where('recorded_at', '>=', $from)
                ->when($settledUntil !== null, fn($q) => $q->where('recorded_at', '>', $settledUntil))
                ->update(['processed_at' => null]);
        });
    }

    /**
     * Offene Besuche ohne Punkt seit `$hours` Stunden schließen (MVP-1186),
     * damit ihr Vorschlag entsteht; danach läuft die Materialisierung je Nutzer.
     */
    public function closeStaleVisits(int $hours): int {
        $cutoff = Carbon::now()->subHours($hours);
        $stale = LocationVisit::query()
            ->withoutGlobalScopes()
            ->where('status', LocationVisitStatus::Open)
            ->where('left_at', '<', $cutoff)
            ->with('geofence')
            ->get();
        $closed = 0;
        $userIds = [];
        foreach ($stale as $visit) {
            $this->closeVisit($visit);
            $closed++;
            $userIds[(int) $visit->user_id] = (int) $visit->organization_id;
        }
        foreach ($userIds as $userId => $orgId) {
            $user = User::query()->find($userId);
            $organization = Organization::query()->find($orgId);
            if ($user instanceof User && $organization instanceof Organization) {
                OrganizationContext::run($organization, fn() => $this->materializer->materializeForUser($user));
            }
        }

        return $closed;
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
            ->where('status', LocationVisitStatus::Open)
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
