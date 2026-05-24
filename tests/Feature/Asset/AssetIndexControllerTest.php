<?php

/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetIndexControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetClass;
use App\Enums\Asset\AssetStatus;
use App\Enums\User\UserRole;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetIndexControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_asset_permission_cannot_access_index(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('assets.index'))
            ->assertForbidden();
    }

    public function test_teamleitung_can_view_asset_index(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Nord',
        ]);

        Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_no' => 'AS-2026-0001',
            'asset_class' => AssetClass::Device->value,
            'name' => 'Sensor A',
            'serial_no' => 'SN-ALPHA',
            'location_text' => 'Werkhalle Nord',
            'customer_id' => $customer->id,
            'status' => AssetStatus::Active->value,
        ]);

        $this->actingAs($user)
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSeeText('Objekte & Assets')
            ->assertSee('AS-2026-0001')
            ->assertSee('Sensor A')
            ->assertSee('SN-ALPHA')
            ->assertSee('Werkhalle Nord')
            ->assertSee('Kunde Nord');
    }

    public function test_index_can_filter_by_query_class_and_status(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_no' => 'AS-2026-0042',
            'asset_class' => AssetClass::Vehicle->value,
            'name' => 'Servicewagen 1',
            'serial_no' => 'VEH-42',
            'status' => AssetStatus::Blocked->value,
        ]);

        Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_no' => 'AS-2026-0099',
            'asset_class' => AssetClass::Tool->value,
            'name' => 'Bohrhammer',
            'serial_no' => 'TOOL-99',
            'status' => AssetStatus::Active->value,
        ]);

        $this->actingAs($user)
            ->get(route('assets.index', [
                'q' => '42',
                'class' => AssetClass::Vehicle->value,
                'status' => AssetStatus::Blocked->value,
            ]))
            ->assertOk()
            ->assertSee('AS-2026-0042')
            ->assertSee('Servicewagen 1')
            ->assertSee('Suche: 42')
            ->assertSee('Typ: Fahrzeug')
            ->assertSee('Status: Gesperrt')
            ->assertDontSee('Bohrhammer');
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
