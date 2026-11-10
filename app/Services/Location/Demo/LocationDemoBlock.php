<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location\Demo;

use App\Models\Customer\Customer;
use App\Models\Location\{CustomerGeofence, LocationPendingEntry, LocationPendingTrip, LocationPoint, LocationVisit};
use App\Models\Platform\Organization;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Location\{LocationIngestService, VisitBuilder, VisitMaterializer};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Standorterfassung zur Vorführung (MVP-1533): zwei Einsatzorte bei Kunden
 * der Musterbranche und zwei Einsätze einer Demo-Person, deren Aufenthalte zu
 * Buchungsvorschlägen in der Prüfliste werden. Die Koordinaten sind erfunden
 * (glatte Werte, keine Anschrift); Punkte gibt es nur für Demo-Nutzer.
 */
final class LocationDemoBlock implements DemoBlock {
    /** Erfundene Zonenmittelpunkte (glatte Werte, rund 1,8 km auseinander). */
    private const array CENTERS = [[51.0, 10.0], [51.01, 10.02]];

    public function __construct(
        private readonly LocationIngestService $ingest,
        private readonly VisitBuilder $visits,
        private readonly VisitMaterializer $materializer,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        $worker = $context->users->get(1);
        if (! $context->moduleActive('module.standorterfassung') || $admin === null || $worker === null) {
            return ['geofences' => 0, 'location_pending_entries' => 0];
        }
        $organization = $context->organization;

        /** @var list<array{name: string, city: string}> $places */
        $places = $context->blueprint['customers'];
        $zones = [];
        foreach (self::CENTERS as $index => [$lat, $lng]) {
            $customer = Customer::query()->where('organization_id', $organization->id)->where('name', $places[$index]['name'] ?? '')->first();
            if (! $customer instanceof Customer) {
                continue;
            }
            $zones[] = CustomerGeofence::query()->create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'label' => (string) __('demo.location.zone_label', ['customer' => $customer->name]),
                'center_lat' => $lat,
                'center_lng' => $lng,
                'radius_m' => 150,
                'min_dwell_minutes' => 10,
                'gap_merge_minutes' => 15,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }
        if ($zones === []) {
            return ['geofences' => 0, 'location_pending_entries' => 0];
        }

        // Je ein Vormittag vorgestern in Zone 1 und gestern in Zone 2: Anfahrt, zwei Stunden vor Ort, Abfahrt.
        // Die Tage liegen mehr als TripBuilder::MAX_GAP_HOURS auseinander, so entsteht keine Fahrt und
        // kein Kartenabgleich über das Netz.
        $yesterday = CarbonImmutable::now(Tz::ofOrganization($organization))->subDay()->startOfDay();
        $points = [];
        foreach (self::CENTERS as $index => [$lat, $lng]) {
            $day = $yesterday->subDays(count(self::CENTERS) - 1 - $index);
            $track = [[7, 40, $lat + 0.03, $lng + 0.05]];
            for ($step = 0; $step <= 6; $step++) {
                $track[] = [8 + intdiv($step * 20, 60), ($step * 20) % 60, $lat + ($step % 2) * 0.0002, $lng + ($step % 3) * 0.0002];
            }
            $track[] = [10, 20, $lat + 0.03, $lng + 0.05];
            foreach ($track as [$hour, $minute, $pointLat, $pointLng]) {
                $points[] = [
                    'lat' => (float) $pointLat,
                    'lng' => (float) $pointLng,
                    'recorded_at' => Carbon::instance($day->setTime($hour, $minute)->utc()),
                    'accuracy_m' => 15,
                ];
            }
        }
        $this->ingest->ingest($worker, $points, LocationPoint::SOURCE_BROWSER);
        // Dieselben Schritte wie ProcessLocationBatch, aber im Seed-Lauf: Die Prüfliste ist sofort gefüllt, unabhängig von der Queue.
        $this->visits->rebuildForUser($worker);
        $this->materializer->materializeForUser($worker);

        $pending = LocationPendingEntry::query()->where('organization_id', $organization->id)->count();

        return ['geofences' => count($zones), 'location_pending_entries' => $pending];
    }

    public function purge(Organization $organization): void {
        LocationPendingTrip::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        LocationPendingEntry::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        LocationVisit::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        LocationPoint::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        CustomerGeofence::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
