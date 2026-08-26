<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\{Organization, User, Vehicle};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Read-only-REST Fahrzeuge. */
final class VehicleReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_missing_ability_is_forbidden(): void {
        Sanctum::actingAs($this->admin, ['assets:read']);

        $this->getJson(route('api.vehicles.index'))->assertForbidden();
    }

    public function test_index_filters_search_archived_and_pagination(): void {
        Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-WD 100']);
        Vehicle::factory()->electric()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-WD 200']);
        Vehicle::factory()->archived()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-ALT 1']);
        Sanctum::actingAs($this->admin, ['vehicles:read']);

        $page = $this->getJson(route('api.vehicles.index', ['per_page' => 1]))->assertOk();
        $this->assertCount(1, $page->json('data'));
        $this->assertSame(2, $page->json('meta.total'));
        $this->assertSame(3, $this->getJson(route('api.vehicles.index', ['archived' => 1]))->json('meta.total'));

        $electric = $this->getJson(route('api.vehicles.index', ['propulsion' => 'electric']))->assertOk();
        $this->assertCount(1, $electric->json('data'));
        $this->assertSame('B-WD 200', $electric->json('data.0.license_plate'));

        $this->assertCount(2, $this->getJson(route('api.vehicles.index', ['search' => 'B-WD']))->json('data'));
    }

    public function test_show_returns_sqid(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs($this->admin, ['vehicles:read']);

        $this->getJson(route('api.vehicles.show', $vehicle))
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->sqid)
            ->assertJsonPath('data.license_plate', $vehicle->license_plate);
    }

    public function test_foreign_organization_vehicle_is_not_found(): void {
        $other = Organization::factory()->create();
        $foreign = Vehicle::factory()->create(['organization_id' => $other->id]);
        Sanctum::actingAs($this->admin, ['vehicles:read']);

        $this->getJson(route('api.vehicles.show', $foreign))->assertNotFound();
        $this->assertCount(0, $this->getJson(route('api.vehicles.index'))->json('data'));
    }
}
