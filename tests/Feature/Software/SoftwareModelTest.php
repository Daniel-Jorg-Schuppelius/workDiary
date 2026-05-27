<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareModelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Software;

use App\Enums\Software\{SoftwareKind, SoftwareLicenseType};
use App\Models\{Asset, Software, SoftwareInstallation};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SoftwareModelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_software_persists_with_enums(): void {
        $software = Software::factory()
            ->for($this->organization)
            ->operatingSystem()
            ->create(['name' => 'Windows 11', 'vendor' => 'Microsoft']);

        $fresh = $software->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(SoftwareKind::OperatingSystem, $fresh->kind);
        $this->assertSame(SoftwareLicenseType::Oem, $fresh->license_type);
    }

    public function test_license_key_is_encrypted_at_rest(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $software = Software::factory()->for($this->organization)->create();

        $install = SoftwareInstallation::factory()
            ->for($this->organization)
            ->for($asset)
            ->for($software)
            ->create(['license_key' => 'SECRET-KEY-123']);

        $raw = \DB::table('software_installations')->where('id', $install->id)->value('license_key');
        $this->assertNotSame('SECRET-KEY-123', $raw);
        $fresh = $install->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('SECRET-KEY-123', $fresh->license_key);
    }

    public function test_only_one_operating_system_per_asset(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $os1 = Software::factory()->for($this->organization)->operatingSystem()->create();
        $os2 = Software::factory()->for($this->organization)->operatingSystem()->create();

        SoftwareInstallation::factory()
            ->for($this->organization)->for($asset)->for($os1)
            ->operatingSystem()->create();

        $this->expectException(QueryException::class);

        SoftwareInstallation::factory()
            ->for($this->organization)->for($asset)->for($os2)
            ->operatingSystem()->create();
    }

    public function test_asset_relations_resolve(): void {
        $asset = Asset::factory()->for($this->organization)->create();
        $os = Software::factory()->for($this->organization)->operatingSystem()->create();
        $app = Software::factory()->for($this->organization)->create();

        SoftwareInstallation::factory()
            ->for($this->organization)->for($asset)->for($os)
            ->operatingSystem()->create();

        SoftwareInstallation::factory()
            ->for($this->organization)->for($asset)->for($app)
            ->create();

        $this->assertCount(2, $asset->softwareInstallations);
        $this->assertNotNull($asset->operatingSystem);
        $this->assertSame($os->id, $asset->operatingSystem->software_id);
    }
}
