<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Enums\Location\LocationVisitStatus;
use App\Models\Customer\Customer;
use App\Models\Location\{CustomerGeofence, LocationVisit};
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Kommandos der Standorterfassung (Phase 146: MVP-1181 Zonen, MVP-1186 verwaiste Besuche). */
class LocationCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_zones_from_customers_creates_one_zone_per_located_customer(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => null, 'address_lng' => null]); // ohne Koordinaten

        $this->artisan('location:zones-from-customers')
            ->expectsOutputToContain('1 Zone(n) angelegt.')
            ->assertSuccessful();

        $this->assertSame(1, CustomerGeofence::query()->count());

        // Zweiter Lauf legt nichts doppelt an.
        $this->artisan('location:zones-from-customers')->expectsOutputToContain('0 Zone(n) angelegt.')->assertSuccessful();
        $this->assertSame(1, CustomerGeofence::query()->count());
    }

    public function test_zones_from_customers_can_be_limited_to_one_organization(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        $other = Organization::factory()->create();

        $this->artisan('location:zones-from-customers', ['--organization' => $other->id])
            ->expectsOutputToContain('0 Zone(n) angelegt.')
            ->assertSuccessful();

        $this->assertSame(0, CustomerGeofence::query()->withoutGlobalScopes()->count());
    }

    public function test_close_stale_visits_closes_only_visits_without_recent_points(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $zone = CustomerGeofence::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'label' => 'Zentrale',
            'center_lat' => 52.52,
            'center_lng' => 13.405,
            'radius_m' => 100,
            'min_dwell_minutes' => 5,
            'gap_merge_minutes' => 10,
            'is_active' => true,
        ]);
        Carbon::setTestNow('2026-06-29 20:00:00');
        $stale = $this->openVisit($user->id, $zone->id, '2026-06-29 08:00:00', '2026-06-29 09:00:00');
        $fresh = $this->openVisit($user->id, $zone->id, '2026-06-29 18:00:00', '2026-06-29 19:30:00');

        $this->artisan('location:close-stale-visits', ['--hours' => 6])
            ->expectsOutputToContain('1 verwaiste(r) Besuch(e) geschlossen.')
            ->assertSuccessful();

        $this->assertSame(LocationVisitStatus::Closed, $stale->fresh()->status);
        $this->assertSame(60, $stale->fresh()->duration_min);
        $this->assertSame(LocationVisitStatus::Open, $fresh->fresh()->status);
    }

    public function test_close_stale_visits_without_visits_reports_zero(): void {
        $this->artisan('location:close-stale-visits')->expectsOutputToContain('0 verwaiste(r) Besuch(e) geschlossen.')->assertSuccessful();
    }

    private function openVisit(int $userId, int $zoneId, string $enteredAt, string $lastPointAt): LocationVisit {
        return LocationVisit::create([
            'organization_id' => $this->organization->id,
            'user_id' => $userId,
            'customer_geofence_id' => $zoneId,
            'entered_at' => $enteredAt,
            'left_at' => $lastPointAt,
            'duration_min' => null,
            'sample_count' => 3,
            'status' => LocationVisitStatus::Open,
            'materialized' => false,
        ]);
    }
}
