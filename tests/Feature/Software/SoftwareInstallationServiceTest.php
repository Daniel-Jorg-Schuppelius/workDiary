<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInstallationServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Software;

use App\Exceptions\SoftwareInstallationException;
use App\Models\{Asset, Organization, Software, User};
use App\Services\Software\SoftwareInstallationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SoftwareInstallationServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private SoftwareInstallationService $service;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->service = app(SoftwareInstallationService::class);
        $this->actor = User::factory()->for($this->organization)->create();
    }

    public function test_attach_creates_installation_with_software_default_version(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $software = Software::factory()->for($this->organization)->create(['default_version' => '2024.1']);

        $install = $this->service->attach($asset, $software, $this->actor, [
            'license_key' => 'KEY-XYZ',
            'seats' => '5',
        ]);

        $this->assertSame($asset->id, $install->asset_id);
        $this->assertSame($software->id, $install->software_id);
        $this->assertSame('2024.1', $install->version);
        $this->assertSame(5, $install->seats);
        $this->assertFalse($install->is_operating_system);
        $this->assertSame('KEY-XYZ', $install->license_key);
    }

    public function test_attach_blocks_second_operating_system(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $os1 = Software::factory()->for($this->organization)->operatingSystem()->create();
        $os2 = Software::factory()->for($this->organization)->operatingSystem()->create();

        $this->service->attach($asset, $os1, $this->actor, ['is_operating_system' => true]);

        $this->expectException(SoftwareInstallationException::class);
        $this->service->attach($asset, $os2, $this->actor, ['is_operating_system' => true]);
    }

    public function test_attach_rejects_cross_organization(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $otherOrg = Organization::factory()->create();
        $software = Software::factory()->for($otherOrg)->create();

        $this->expectException(SoftwareInstallationException::class);
        $this->service->attach($asset, $software, $this->actor, []);
    }

    public function test_update_promotes_to_operating_system_only_when_none_present(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $app = Software::factory()->for($this->organization)->create();
        $install = $this->service->attach($asset, $app, $this->actor, []);

        $this->service->update($install, $this->actor, ['is_operating_system' => true, 'version' => '11']);

        $fresh = $install->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_operating_system);
        $this->assertSame('11', $fresh->version);
    }

    public function test_update_blocks_promotion_when_os_exists(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $os = Software::factory()->for($this->organization)->operatingSystem()->create();
        $app = Software::factory()->for($this->organization)->create();

        $this->service->attach($asset, $os, $this->actor, ['is_operating_system' => true]);
        $appInstall = $this->service->attach($asset, $app, $this->actor, []);

        $this->expectException(SoftwareInstallationException::class);
        $this->service->update($appInstall, $this->actor, ['is_operating_system' => true]);
    }

    public function test_detach_removes_installation(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $software = Software::factory()->for($this->organization)->create();
        $install = $this->service->attach($asset, $software, $this->actor, []);

        $this->service->detach($install, $this->actor);

        $this->assertDatabaseMissing('software_installations', ['id' => $install->id]);
    }

    public function test_asset_room_mismatch_with_customer_is_rejected_by_service(): void {
        $customer = \App\Models\Customer::factory()->for($this->organization)->create();
        $otherCustomer = \App\Models\Customer::factory()->for($this->organization)->create();
        $room = \App\Models\Room::factory()->for($this->organization)->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $service = app(\App\Services\Asset\AssetService::class);

        $this->expectException(\App\Exceptions\AssetValidationException::class);
        $service->create($this->actor, [
            'asset_class' => \App\Enums\Asset\AssetClass::Device->value,
            'name' => 'Test',
            'status' => \App\Enums\Asset\AssetStatus::Active->value,
            'customer_id' => $customer->id,
            'room_id' => $room->id,
        ]);
    }
}
