<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Software;

use App\Enums\Software\{SoftwareKind, SoftwareLicenseType};
use App\Enums\User\UserRole;
use App\Models\{Asset, Software, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SoftwareControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_asset_permission_cannot_view_index(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('software.index'))
            ->assertForbidden();
    }

    public function test_teamleitung_can_view_index_with_filter(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        Software::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Office',
            'kind' => SoftwareKind::Application->value,
        ]);
        Software::factory()->operatingSystem()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Foo OS',
        ]);

        $this->actingAs($user)
            ->get(route('software.index', ['q' => 'Acme']))
            ->assertOk()
            ->assertSee('Acme Office')
            ->assertDontSee('Foo OS');
    }

    public function test_store_creates_software(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $response = $this->actingAs($user)->post(route('software.store'), [
            'name' => 'Neue Software',
            'vendor' => 'Acme',
            'kind' => SoftwareKind::Application->value,
            'license_type' => SoftwareLicenseType::Subscription->value,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('software.index'));
        $this->assertDatabaseHas('software', [
            'organization_id' => $this->organization->id,
            'name' => 'Neue Software',
            'vendor' => 'Acme',
        ]);
    }

    public function test_store_rejects_duplicate_name_vendor(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        Software::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Dup',
            'vendor' => 'Acme',
        ]);

        $response = $this->actingAs($user)->post(route('software.store'), [
            'name' => 'Dup',
            'vendor' => 'Acme',
            'kind' => SoftwareKind::Application->value,
            'license_type' => SoftwareLicenseType::Subscription->value,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_update_can_keep_same_name(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $software = Software::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Keep',
            'vendor' => 'Acme',
        ]);

        $response = $this->actingAs($user)->put(route('software.update', $software), [
            'name' => 'Keep',
            'vendor' => 'Acme',
            'kind' => SoftwareKind::Application->value,
            'license_type' => SoftwareLicenseType::Perpetual->value,
        ]);

        $response->assertRedirect(route('software.index'));
        $this->assertSame(SoftwareLicenseType::Perpetual, $software->refresh()->license_type);
    }

    public function test_destroy_blocks_when_in_use(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $software = Software::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $asset->softwareInstallations()->create([
            'organization_id' => $this->organization->id,
            'software_id' => $software->id,
            'is_operating_system' => false,
        ]);

        $response = $this->actingAs($user)->delete(route('software.destroy', $software));

        $response->assertSessionHasErrors('software');
        $this->assertDatabaseHas('software', ['id' => $software->id]);
    }

    public function test_destroy_removes_unused_software(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $software = Software::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->delete(route('software.destroy', $software))
            ->assertRedirect(route('software.index'));

        $this->assertDatabaseMissing('software', ['id' => $software->id]);
    }
}
