<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetBlockOverridePermissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\AssetCompliance;

use App\Enums\User\{Permission, UserRole};
use App\Models\AssetCompliance\AssetComplianceProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/** Befristete Ausnahmen von Sperren: assetCompliance.release und asset.block.override (lk-06). */
class AssetBlockOverridePermissionTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    public function test_block_exceptions_need_release_and_override(): void {
        $this->setUpOrganization();

        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::Geschaeftsfuehrung->value))->allows('release', AssetComplianceProfile::class));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Teamleitung->value))->allows('release', AssetComplianceProfile::class));
        $this->assertFalse(Gate::forUser($this->actorIn($this->organization, [Permission::AssetComplianceRelease]))->allows('release', AssetComplianceProfile::class));
        $this->assertTrue(Gate::forUser($this->actorIn($this->organization, [Permission::AssetComplianceRelease, Permission::AssetBlockOverride]))->allows('release', AssetComplianceProfile::class));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('release', AssetComplianceProfile::class));
    }
}
