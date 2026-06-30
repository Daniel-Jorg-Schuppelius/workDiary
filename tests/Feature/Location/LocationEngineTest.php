<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationEngineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Models\Customer;
use App\Models\Location\{CustomerGeofence, LocationPoint, LocationVisit};
use App\Models\User;
use App\Services\Location\{GeofenceMatcher, VisitBuilder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LocationEngineTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private CustomerGeofence $geofence;

    // Referenz-Mittelpunkt (Berlin Mitte).
    private const LAT = 52.5200000;

    private const LNG = 13.4050000;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->geofence = CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'label' => 'Hauptsitz',
            'center_lat' => self::LAT,
            'center_lng' => self::LNG,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);
    }

    private function point(float $lat, float $lng, int $minuteOffset, ?int $accuracy = null): void {
        LocationPoint::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'recorded_at' => Carbon::parse('2026-06-29 08:00:00')->addMinutes($minuteOffset),
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracy,
            'source' => LocationPoint::SOURCE_OWNTRACKS,
        ]);
    }

    public function test_distance_matcher_is_accurate(): void {
        // ~0.0001° Breite ≈ 11.1 m.
        $distance = GeofenceMatcher::distanceMeters(self::LAT, self::LNG, self::LAT + 0.0001, self::LNG);
        $this->assertEqualsWithDelta(11.1, $distance, 0.5);

        $matcher = new GeofenceMatcher();
        $this->assertNotNull($matcher->match(self::LAT + 0.0001, self::LNG, [$this->geofence]));
        $this->assertNull($matcher->match(self::LAT + 0.01, self::LNG, [$this->geofence])); // ~1.1 km
    }

    public function test_dwell_produces_a_closed_visit(): void {
        // 30 Minuten im Geofence, danach weit weg → Besuch wird geschlossen.
        foreach ([0, 5, 10, 15, 20, 25, 30] as $offset) {
            $this->point(self::LAT + 0.0001, self::LNG, $offset);
        }
        $this->point(self::LAT + 0.01, self::LNG, 45); // außerhalb, Lücke 15 min > 10

        $processed = app(VisitBuilder::class)->rebuildForUser($this->user);

        $this->assertSame(8, $processed);
        $visits = LocationVisit::query()->get();
        $this->assertCount(1, $visits);

        $visit = $visits->first();
        $this->assertSame(LocationVisit::STATUS_CLOSED, $visit->status);
        $this->assertSame(30, $visit->duration_min);
        $this->assertSame(7, $visit->sample_count);
        $this->assertSame($this->geofence->id, $visit->customer_geofence_id);
    }

    public function test_passthrough_below_min_dwell_is_discarded(): void {
        $this->point(self::LAT, self::LNG, 0);
        $this->point(self::LAT, self::LNG, 2); // nur 2 min < min_dwell 5
        $this->point(self::LAT + 0.01, self::LNG, 20); // weg

        app(VisitBuilder::class)->rebuildForUser($this->user);

        $this->assertSame(0, LocationVisit::query()->count());
    }

    public function test_short_gap_does_not_split_visit(): void {
        // Kurzer Aussetzer (ein Punkt knapp außerhalb innerhalb der Toleranz),
        // danach wieder im Geofence → ein zusammenhängender Besuch.
        $this->point(self::LAT, self::LNG, 0);
        $this->point(self::LAT, self::LNG, 5);
        $this->point(self::LAT + 0.003, self::LNG, 12); // ~330 m außerhalb, Lücke 7 < 10
        $this->point(self::LAT, self::LNG, 16);
        $this->point(self::LAT, self::LNG, 20);
        $this->point(self::LAT + 0.01, self::LNG, 40); // endgültig weg

        app(VisitBuilder::class)->rebuildForUser($this->user);

        $visits = LocationVisit::query()->get();
        $this->assertCount(1, $visits);
        $this->assertSame(20, $visits->first()->duration_min);
    }

    public function test_inaccurate_points_are_ignored(): void {
        $this->point(self::LAT, self::LNG, 0, accuracy: 1000); // verworfen
        $this->point(self::LAT + 0.01, self::LNG, 5, accuracy: 5); // weit weg, präzise

        app(VisitBuilder::class)->rebuildForUser($this->user);

        $this->assertSame(0, LocationVisit::query()->count());
        // Beide Punkte gelten als verarbeitet.
        $this->assertSame(0, LocationPoint::query()->whereNull('processed_at')->count());
    }
}
