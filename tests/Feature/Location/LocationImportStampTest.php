<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationImportStampTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Http\Controllers\Api\LocationController;
use App\Models\{Customer, User};
use App\Models\Location\{CustomerGeofence, LocationPendingEntry, LocationPoint, LocationVisit};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Location\GoogleTimelineImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LocationImportStampTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        config(['license.feature_overrides' => [LocationController::MODULE => true]]);
        app(FeatureFlagResolver::class)->flush();
    }

    public function test_browser_stamp_stores_point(): void {
        $this->user->setPreference(LocationController::OPT_IN_PREFERENCE, true);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/location/stamp', ['lat' => 52.52, 'lng' => 13.405, 'accuracy_m' => 8])
            ->assertOk()
            ->assertJson(['stored' => 1]);

        $this->assertSame(1, LocationPoint::query()->where('source', LocationPoint::SOURCE_BROWSER)->count());
    }

    public function test_browser_stamp_requires_opt_in(): void {
        $this->user->setPreference(LocationController::OPT_IN_PREFERENCE, false);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/location/stamp', ['lat' => 52.52, 'lng' => 13.405])
            ->assertStatus(403);

        $this->assertSame(0, LocationPoint::query()->count());
    }

    public function test_google_records_import(): void {
        $json = json_encode(['locations' => [
            ['latitudeE7' => 525200000, 'longitudeE7' => 134050000, 'timestamp' => '2026-06-20T08:00:00Z', 'accuracy' => 12],
            ['latitudeE7' => 525201000, 'longitudeE7' => 134050000, 'timestamp' => '2026-06-20T08:05:00Z', 'accuracy' => 9],
            ['latitudeE7' => 0, 'longitudeE7' => 0], // ohne Zeit → verworfen
        ]]);

        $file = UploadedFile::fake()->createWithContent('Records.json', $json);

        $this->actingAs($this->user)
            ->post(route('location.devices.import-google'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(2, LocationPoint::query()->where('source', LocationPoint::SOURCE_GOOGLE)->count());
        $this->assertTrue((bool) $this->user->fresh()->getPreference(LocationController::OPT_IN_PREFERENCE, false));
    }

    public function test_google_semantic_import(): void {
        $json = json_encode(['semanticSegments' => [[
            'timelinePath' => [
                ['point' => '52.5200000°, 13.4050000°', 'time' => '2026-06-20T09:00:00Z'],
                ['point' => '52.5210000°, 13.4050000°', 'time' => '2026-06-20T09:10:00Z'],
            ],
        ]]]);

        $file = UploadedFile::fake()->createWithContent('timeline.json', $json);

        $this->actingAs($this->user)
            ->post(route('location.devices.import-google'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(2, LocationPoint::query()->where('source', LocationPoint::SOURCE_GOOGLE)->count());
    }

    /**
     * Ein Aufenthalt steht im on-device-Export als visit-Segment ohne
     * Bewegungsspur: liegt das Handy still, gibt es keinen timelinePath. Der
     * Import muss daraus Beginn und Ende erzeugen, sonst verschwindet der
     * gesamte Kundentermin.
     */
    public function test_google_visit_segment_becomes_pending_entry(): void {
        $this->geofenceAt(52.52, 13.405);

        $json = json_encode(['semanticSegments' => [
            [
                'startTime' => '2026-06-20T08:00:00+02:00',
                'endTime' => '2026-06-20T10:00:00+02:00',
                'visit' => ['topCandidate' => ['placeLocation' => ['latLng' => '52.5200000°, 13.4050000°']]],
            ],
            [
                'startTime' => '2026-06-20T10:00:00+02:00',
                'endTime' => '2026-06-20T10:30:00+02:00',
                'activity' => [
                    'start' => ['latLng' => 'geo:52.520000,13.405000'],
                    'end' => ['latLng' => 'geo:52.600000,13.500000'],
                ],
            ],
        ]]);

        $file = UploadedFile::fake()->createWithContent('timeline.json', $json);

        $this->actingAs($this->user)
            ->post(route('location.devices.import-google'), ['file' => $file])
            ->assertRedirect();

        // Aufenthaltsende und Fahrtbeginn sind derselbe Punkt → dedupliziert.
        $this->assertSame(3, LocationPoint::query()->count());

        $visit = LocationVisit::query()->sole();
        $this->assertSame(LocationVisit::STATUS_CLOSED, $visit->status);
        $this->assertSame(120, (int) $visit->duration_min);

        $pending = LocationPendingEntry::query()->sole();
        $this->assertSame(120, (int) $pending->minutes);
    }

    /**
     * Der Trenner zwischen den Koordinaten darf das Minus der zweiten Zahl
     * nicht verschlucken – sonst landen Orte westlich von Greenwich auf der
     * falschen Erdhälfte.
     */
    public function test_parse_keeps_negative_longitude(): void {
        $points = app(GoogleTimelineImporter::class)->parse((string) json_encode(['semanticSegments' => [[
            'timelinePath' => [
                ['point' => '51.5074°, -0.1278°', 'time' => '2026-06-20T09:00:00Z'],
                ['point' => 'geo:51.507400,-0.127900', 'time' => '2026-06-20T09:05:00Z'],
            ],
        ]]]));

        $this->assertCount(2, $points);
        $this->assertEqualsWithDelta(51.5074, $points[0]['lat'], 0.00001);
        $this->assertEqualsWithDelta(-0.1278, $points[0]['lng'], 0.00001);
        $this->assertEqualsWithDelta(-0.1279, $points[1]['lng'], 0.00001);
    }

    /** Ältere Archive (Takeout) und Rohsignale mit Genauigkeitsangabe. */
    public function test_parse_reads_takeout_objects_and_raw_signals(): void {
        $points = app(GoogleTimelineImporter::class)->parse((string) json_encode([
            'timelineObjects' => [[
                'placeVisit' => [
                    'location' => ['latitudeE7' => 525200000, 'longitudeE7' => 134050000],
                    'duration' => ['startTimestamp' => '2026-06-20T08:00:00Z', 'endTimestamp' => '2026-06-20T09:00:00Z'],
                ],
            ]],
            'rawSignals' => [
                ['position' => ['LatLng' => 'geo:52.520000,13.405000', 'accuracyMeters' => 14, 'timestamp' => '2026-06-20T10:00:00Z']],
                ['activityRecord' => ['probability' => 0.9]], // kein Positionssignal
            ],
        ]));

        $this->assertCount(3, $points);
        $this->assertEqualsWithDelta(52.52, $points[0]['lat'], 0.00001);
        $this->assertNull($points[0]['accuracy_m']);
        $this->assertSame(14, $points[2]['accuracy_m']);
    }

    /** iOS exportiert dieselben Segmente ohne den Schlüssel "semanticSegments". */
    public function test_parse_accepts_top_level_segment_list(): void {
        $points = app(GoogleTimelineImporter::class)->parse((string) json_encode([[
            'startTime' => '2026-06-20T08:00:00Z',
            'endTime' => '2026-06-20T08:45:00Z',
            'visit' => ['topCandidate' => ['placeLocation' => ['latLng' => 'geo:52.520000,13.405000']]],
        ]]));

        $this->assertCount(2, $points);
        $this->assertSame(45, (int) $points[0]['recorded_at']->diffInMinutes($points[1]['recorded_at']));
    }

    private function geofenceAt(float $lat, float $lng): CustomerGeofence {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        return CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'label' => 'Hauptsitz',
            'center_lat' => $lat,
            'center_lng' => $lng,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);
    }
}
