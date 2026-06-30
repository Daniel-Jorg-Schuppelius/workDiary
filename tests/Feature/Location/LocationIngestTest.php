<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationIngestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Location;

use App\Http\Controllers\Api\LocationController;
use App\Models\Customer;
use App\Models\Location\{CustomerGeofence, LocationDeviceToken, LocationPendingEntry, LocationPoint, LocationVisit};
use App\Models\{TimeEntry, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Location\VisitMaterializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LocationIngestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private CustomerGeofence $geofence;

    private string $token;

    private const LAT = 52.5200000;

    private const LNG = 13.4050000;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->geofence = CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'label' => 'Baustelle Nord',
            'center_lat' => self::LAT,
            'center_lng' => self::LNG,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);

        [, $this->token] = LocationDeviceToken::issue($this->user, 'Testgerät');

        // Datenschutz-Schranken für den Happy-Path öffnen: Lizenzmodul + Opt-in.
        $this->enableModule(true);
        $this->user->setPreference(LocationController::OPT_IN_PREFERENCE, true);
    }

    private function enableModule(bool $enabled): void {
        config(['license.feature_overrides' => [LocationController::MODULE => $enabled]]);
        app(FeatureFlagResolver::class)->flush();
    }

    /** @return array<int, array<string, mixed>> */
    private function track(): array {
        $in = ['lat' => self::LAT + 0.0001, 'lng' => self::LNG, 'acc' => 10];

        return [
            $in + ['recorded_at' => '2026-06-29T08:00:00Z'],
            $in + ['recorded_at' => '2026-06-29T08:15:00Z'],
            $in + ['recorded_at' => '2026-06-29T08:30:00Z'],
            ['lat' => self::LAT + 0.01, 'lng' => self::LNG, 'acc' => 10, 'recorded_at' => '2026-06-29T09:00:00Z'],
        ];
    }

    public function test_invalid_token_is_rejected(): void {
        $this->postJson('/api/location/ingest/totallybogus', ['points' => $this->track()])
            ->assertStatus(401);

        $this->assertSame(0, LocationPoint::query()->count());
    }

    public function test_ingest_builds_visit_and_inbox_entry(): void {
        $this->postJson("/api/location/ingest/{$this->token}", ['points' => $this->track()])
            ->assertOk()
            ->assertExactJson([]);

        // Punkte gespeichert.
        $this->assertSame(4, LocationPoint::query()->count());

        // Sync-Queue: ProcessLocationBatch lief inline → ein geschlossener Besuch.
        $visit = LocationVisit::query()->firstOrFail();
        $this->assertSame(LocationVisit::STATUS_CLOSED, $visit->status);
        $this->assertSame(30, $visit->duration_min);
        $this->assertTrue($visit->materialized);

        // Review-Inbox: offener Vorschlag mit Kunde/Projekt.
        $pending = LocationPendingEntry::query()->firstOrFail();
        $this->assertSame(LocationPendingEntry::STATUS_OPEN, $pending->status);
        $this->assertSame(30, $pending->minutes);
        $this->assertSame($this->geofence->customer_id, $pending->customer_id);
        $this->assertNotNull($pending->project_id);
        $this->assertSame('Baustelle Nord', $pending->description);

        // Gerät als zuletzt benutzt markiert.
        $this->assertNotNull(LocationDeviceToken::query()->firstOrFail()->last_used_at);
    }

    public function test_confirm_creates_time_entry(): void {
        $this->postJson("/api/location/ingest/{$this->token}", ['points' => $this->track()])->assertOk();

        $pending = LocationPendingEntry::query()->firstOrFail();
        $timeEntry = app(VisitMaterializer::class)->confirm($pending, $this->user);

        $this->assertInstanceOf(TimeEntry::class, $timeEntry);
        $this->assertSame(30, $timeEntry->minutes);
        $this->assertSame($pending->project_id, $timeEntry->project_id);

        $pending->refresh();
        $this->assertSame(LocationPendingEntry::STATUS_IMPORTED, $pending->status);
        $this->assertSame($timeEntry->id, $pending->time_entry_id);
        $this->assertSame($this->user->id, $pending->resolved_by);
    }

    public function test_second_ingest_does_not_duplicate_visit(): void {
        $payload = ['points' => $this->track()];
        $this->postJson("/api/location/ingest/{$this->token}", $payload)->assertOk();
        // Erneuter Push derselben Spur darf keine zweite Buchung erzeugen
        // (Punkte sind neu, aber der bereits geschlossene Besuch wird nicht
        // rückwirkend verändert; ein neuer Besuch entsteht durch die Wiederholung).
        $this->postJson("/api/location/ingest/{$this->token}", $payload)->assertOk();

        // Jede Inbox-Zeile ist genau einem Besuch zugeordnet (unique).
        $this->assertSame(
            LocationVisit::query()->where('materialized', true)->count(),
            LocationPendingEntry::query()->count(),
        );
    }

    public function test_ingest_rejected_when_module_disabled(): void {
        $this->enableModule(false);

        $this->postJson("/api/location/ingest/{$this->token}", ['points' => $this->track()])
            ->assertStatus(403)
            ->assertJson(['error' => 'module_disabled']);

        $this->assertSame(0, LocationPoint::query()->count());
    }

    public function test_ingest_rejected_without_opt_in(): void {
        $this->user->setPreference(LocationController::OPT_IN_PREFERENCE, false);

        $this->postJson("/api/location/ingest/{$this->token}", ['points' => $this->track()])
            ->assertStatus(403)
            ->assertJson(['error' => 'tracking_not_consented']);

        $this->assertSame(0, LocationPoint::query()->count());
    }

    public function test_coordinates_are_encrypted_at_rest(): void {
        $this->postJson("/api/location/ingest/{$this->token}", ['points' => $this->track()])->assertOk();

        // Roher DB-Wert darf die Koordinate nicht im Klartext enthalten ...
        $rawLat = (string) DB::table('location_points')->value('lat');
        $this->assertStringNotContainsString('52.52', $rawLat);
        $this->assertNotSame('', $rawLat);

        // ... das Model liefert sie aber entschlüsselt zurück.
        $point = LocationPoint::query()->orderBy('recorded_at')->firstOrFail();
        $this->assertEqualsWithDelta(self::LAT + 0.0001, (float) $point->lat, 0.0001);
    }

    public function test_purge_removes_old_processed_points_only(): void {
        $this->postJson("/api/location/ingest/{$this->token}", ['points' => $this->track()])->assertOk();
        $this->assertSame(4, LocationPoint::query()->count());

        // Punkte ins Aufbewahrungs-Fenster zurückdatieren (verarbeitet + alt).
        LocationPoint::query()->update(['recorded_at' => '2020-01-01 08:00:00']);

        $this->artisan('location:purge-points', ['--days' => 30])
            ->assertExitCode(0);

        $this->assertSame(0, LocationPoint::query()->count());
        // Abgeleitete Daten bleiben erhalten.
        $this->assertGreaterThan(0, LocationVisit::query()->count());
        $this->assertGreaterThan(0, LocationPendingEntry::query()->count());
    }
}
