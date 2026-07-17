<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetSoftwareControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\User\UserRole;
use App\Models\{Asset, Software, SoftwareInstallation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetSoftwareControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_can_open_software_create_dialog(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        Software::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Katalog-App']);

        $this->actingAs($user)
            ->get(route('assets.software-installations.create', $asset))
            ->assertOk()
            ->assertSee('Software zuweisen')
            ->assertSee('name="software_id"', false)
            ->assertSee('Katalog-App');
    }

    public function test_can_open_operating_system_dialog(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        Software::factory()->operatingSystem()->create(['organization_id' => $this->organization->id, 'name' => 'Katalog-OS']);

        $this->actingAs($user)
            ->get(route('assets.software-installations.create', ['asset' => $asset, 'os' => 1]))
            ->assertOk()
            ->assertSee('Betriebssystem zuweisen')
            ->assertSee('Katalog-OS');
    }

    public function test_attach_application_to_asset(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $software = Software::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->post(route('assets.software-installations.store', $asset), [
            'software_id' => $software->id,
            'version' => '1.2.3',
            'seats' => 5,
        ]);

        $response->assertRedirect(route('assets.show', $asset));
        $this->assertSame(1, $asset->softwareInstallations()->count());
        $install = $asset->softwareInstallations()->first();
        $this->assertNotNull($install);
        $this->assertSame('1.2.3', $install->version);
        $this->assertFalse((bool) $install->is_operating_system);
    }

    public function test_attach_operating_system_marks_flag(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $os = Software::factory()->operatingSystem()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('assets.software-installations.store', $asset), [
            'software_id' => $os->id,
            'is_operating_system' => 1,
        ])->assertRedirect();

        $this->assertNotNull($asset->refresh()->operatingSystem);
        $this->assertSame($os->id, $asset->operatingSystem->software_id);
    }

    public function test_attaching_second_os_is_blocked(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $osA = Software::factory()->operatingSystem()->create(['organization_id' => $this->organization->id]);
        $osB = Software::factory()->operatingSystem()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('assets.software-installations.store', $asset), [
            'software_id' => $osA->id,
            'is_operating_system' => 1,
        ])->assertRedirect();

        $response = $this->actingAs($user)->post(route('assets.software-installations.store', $asset), [
            'software_id' => $osB->id,
            'is_operating_system' => 1,
        ]);
        $response->assertSessionHasErrors('software_id');

        $osInstalls = SoftwareInstallation::query()
            ->where('asset_id', $asset->id)
            ->where('is_operating_system', true)
            ->get();
        $this->assertCount(1, $osInstalls);
        $this->assertSame($osA->id, $osInstalls->first()?->software_id);
    }

    public function test_detach_removes_installation(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $software = Software::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('assets.software-installations.store', $asset), [
            'software_id' => $software->id,
        ])->assertRedirect();

        $install = $asset->softwareInstallations()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('assets.software-installations.destroy', [$asset, $install]))
            ->assertRedirect(route('assets.show', $asset));

        $this->assertSame(0, $asset->softwareInstallations()->count());
    }
}
