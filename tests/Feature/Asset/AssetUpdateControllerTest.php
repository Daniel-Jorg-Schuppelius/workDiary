<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetUpdateControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetClass, AssetStatus};
use App\Enums\User\UserRole;
use App\Models\{Asset, Customer, Room, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetUpdateControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_update_permission_cannot_open_edit(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('assets.edit', $asset))
            ->assertForbidden();
    }

    public function test_teamleitung_can_open_edit_dialog(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Edit-Sensor',
        ]);

        $this->actingAs($user)
            ->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('Edit-Sensor')
            ->assertSee('name="name"', false);
    }

    public function test_update_persists_room_assignment(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
            'room_id' => null,
        ]);
        $room = Room::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->put(route('assets.update', $asset), [
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
            'name' => 'Neuer Name',
            'room_id' => $room->id,
        ]);

        $response->assertRedirect(route('assets.show', $asset));
        $this->assertSame($room->id, $asset->refresh()->room_id);
        $this->assertSame('Neuer Name', $asset->name);
    }

    public function test_update_syncs_existing_and_new_tags(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
        ]);
        $existing = \App\Models\Tag::create([
            'name' => 'Bestand',
            'organization_id' => $this->organization->id,
        ]);

        $this->actingAs($user)->put(route('assets.update', $asset), [
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
            'name' => 'Getaggtes Asset',
            'tag_ids' => [$existing->sqid],
            'new_tags' => 'Kritisch, Serverraum',
        ])->assertRedirect(route('assets.show', $asset));

        $names = $asset->refresh()->tags()->pluck('name')->sort()->values()->all();
        $this->assertSame(['Bestand', 'Kritisch', 'Serverraum'], $names);
    }

    public function test_update_rejects_room_with_mismatching_customer(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
        ]);
        $room = Room::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customerB->id,
        ]);

        $response = $this->actingAs($user)->put(route('assets.update', $asset), [
            'asset_class' => AssetClass::Device->value,
            'status' => AssetStatus::Active->value,
            'name' => 'Mismatch',
            'customer_id' => $customerA->id,
            'room_id' => $room->id,
        ]);

        $response->assertSessionHasErrors('room_id');
        $this->assertNull($asset->refresh()->room_id);
    }

    public function test_create_prefills_room_from_query(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $room = Room::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Serverraum 42',
        ]);

        $this->actingAs($user)
            ->get(route('assets.create', ['room' => $room->sqid]))
            ->assertOk()
            ->assertSee('Serverraum 42');
    }

    public function test_create_prefills_room_from_numeric_query_fallback(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $room = Room::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Serverraum 99',
        ]);

        $this->actingAs($user)
            ->get(route('assets.create', ['room' => (string) $room->id]))
            ->assertOk()
            ->assertSee('Serverraum 99');
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
