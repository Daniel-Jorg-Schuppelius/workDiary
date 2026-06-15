<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDossierControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetStatus, DefectSeverity, DefectStatus, MaintenanceIntervalKind};
use App\Enums\Facility\RoomRequirementKind;
use App\Enums\Protocol\ProtocolType;
use App\Enums\User\UserRole;
use App\Models\{Asset, AssetAssignment, AssetDefect, MaintenancePlan, Organization, Protocol, Room, RoomRequirement, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetDossierControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_asset_permission_cannot_view_dossier(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('assets.dossier', $asset))
            ->assertForbidden();
    }

    public function test_dossier_renders_all_source_sections_and_lifecycle(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Lebenszyklus Asset',
            'asset_no' => 'AS-2026-DOSS',
            'status' => AssetStatus::Active->value,
            'commissioned_on' => now()->subYear()->toDateString(),
            'warranty_until' => now()->subDay()->toDateString(),
        ]);

        Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ProtocolType::Service->value,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'created_by_user_id' => $user->id,
            'title' => 'Wartungsprotokoll Akte',
        ]);

        AssetAssignment::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $user->id,
            'checked_out_by_user_id' => $user->id,
            'checked_out_at' => now()->subDays(5),
        ]);

        AssetDefect::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'reported_by_user_id' => $user->id,
            'reported_at' => now()->subDays(3),
            'severity' => DefectSeverity::High->value,
            'status' => DefectStatus::Open->value,
            'title' => 'Defekt Akte',
            'blocks_usage' => true,
        ]);

        MaintenancePlan::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'code' => 'MP-DOSS',
            'label' => 'Jahreswartung Akte',
            'interval_kind' => MaintenanceIntervalKind::Months->value,
            'interval_value' => 12,
            'tolerance_days' => 7,
            'next_due_on' => now()->addMonths(11)->toDateString(),
            'last_run_at' => now()->subMonth(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('assets.dossier', $asset));

        $response->assertOk();
        $response->assertViewIs('assets.dossier');
        $response->assertSee('Lebenszyklus Asset');
        $response->assertSee(__('asset.lifecycle.in_operation'));
        $response->assertSee(__('asset.dossier.warranty_expired'));
        $response->assertSee('Wartungsprotokoll Akte');
        $response->assertSee('Defekt Akte');
        $response->assertSee('Jahreswartung Akte');
        $response->assertSee(__('asset.dossier.assignments'));
        $response->assertSee(__('asset.dossier.timeline'));
    }

    public function test_dossier_shows_decommissioned_lifecycle_phase(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => AssetStatus::Decommissioned->value,
            'decommissioned_on' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertSee(__('asset.lifecycle.decommissioned'));
    }

    public function test_dossier_shows_room_requirements_of_located_room(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $room = Room::factory()->create(['organization_id' => $this->organization->id, 'name' => 'OP-Saal']);
        RoomRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'room_id' => $room->id,
            'kind' => RoomRequirementKind::HygieneLevel->value,
            'level' => 'Reinraum',
            'is_active' => true,
        ]);

        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($user)
            ->get(route('assets.dossier', $asset))
            ->assertOk()
            ->assertSee(__('asset.dossier.room_requirements'))
            ->assertSee(RoomRequirementKind::HygieneLevel->label())
            ->assertSee('Reinraum');
    }

    public function test_dossier_autoprints_when_print_param_set(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('assets.dossier', $asset, ['print' => 1]) . '?print=1')
            ->assertOk()
            ->assertSee('window.print()', false);
    }

    public function test_dossier_cross_org_asset_is_not_found(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $otherOrg = Organization::factory()->create();
        $foreignAsset = Asset::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($user)
            ->get(route('assets.dossier', $foreignAsset))
            ->assertNotFound();
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);

        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }
}
