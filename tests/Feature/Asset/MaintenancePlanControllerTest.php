<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\MaintenanceIntervalKind;
use App\Enums\User\UserRole;
use App\Models\{Asset, MaintenancePlan, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MaintenancePlanControllerTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);

        $this->actor = User::factory()->create(['organization_id' => $this->org->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $role = Role::query()
            ->where('name', UserRole::Teamleitung->value)
            ->where('team_id', $this->org->id)
            ->firstOrFail();
        $this->actor->syncRoles([$role]);
        $registrar->forgetCachedPermissions();
        $this->actor->unsetRelation('roles');
        $this->actor->unsetRelation('permissions');

        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->actor);
    }

    public function test_can_open_plan_create_dialog(): void {
        $this->get(route('assets.maintenance-plans.create', $this->asset))
            ->assertOk()
            ->assertSee('Wartungsplan anlegen')
            ->assertSee('name="label"', false)
            ->assertSee('name="interval_kind"', false);
    }

    public function test_store_creates_plan(): void {
        $response = $this->post(route('assets.maintenance-plans.store', $this->asset), [
            'label' => 'Quartalswartung',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 3,
            'tolerance_days' => 7,
        ]);

        $response->assertRedirect(route('assets.show', $this->asset));
        $this->assertDatabaseHas('maintenance_plans', [
            'asset_id' => $this->asset->id,
            'label' => 'Quartalswartung',
        ]);
    }

    public function test_complete_reschedules(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'next_due_on' => '2026-06-01',
        ]);

        $this->post(route('assets.maintenance-plans.complete', [$this->asset, $plan]))
            ->assertRedirect();

        $plan->refresh();
        $this->assertNotNull($plan->last_run_at);
    }

    public function test_toggle_pauses_then_resumes(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'is_active' => true,
        ]);

        $this->post(route('assets.maintenance-plans.toggle', [$this->asset, $plan]))
            ->assertRedirect();
        $this->assertFalse($plan->fresh()?->is_active);

        $this->post(route('assets.maintenance-plans.toggle', [$this->asset, $plan]))
            ->assertRedirect();
        $this->assertTrue($plan->fresh()?->is_active);
    }

    public function test_destroy_removes_plan(): void {
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
        ]);

        $this->delete(route('assets.maintenance-plans.destroy', [$this->asset, $plan]))
            ->assertRedirect();

        $this->assertDatabaseMissing('maintenance_plans', ['id' => $plan->id]);
    }

    public function test_cross_asset_access_is_blocked(): void {
        $otherAsset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $plan = MaintenancePlan::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
        ]);

        $this->delete(route('assets.maintenance-plans.destroy', [$otherAsset, $plan]))
            ->assertNotFound();
    }
}
