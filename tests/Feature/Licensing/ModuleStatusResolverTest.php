<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleStatusResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Models\{LicenseFlagOverride, Organization};
use App\Services\Licensing\ModuleStatusResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleStatusResolverTest extends TestCase {
    use RefreshDatabase;

    private ModuleStatusResolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        $this->resolver = app(ModuleStatusResolver::class);
    }

    public function test_licensed_module_is_active_by_default(): void {
        $org = Organization::factory()->enterprise()->create();

        $this->assertSame(ModuleStatus::Active, $this->resolver->statusFor($org, 'module.lager'));
        $this->assertTrue($this->resolver->isActiveFor($org, 'module.lager'));
    }

    public function test_unlicensed_module_is_not_licensed(): void {
        $org = Organization::factory()->free()->create();

        $this->assertSame(ModuleStatus::NotLicensed, $this->resolver->statusFor($org, 'module.lager'));
        $this->assertFalse($this->resolver->isActiveFor($org, 'module.lager'));
    }

    public function test_customer_disable_marks_module_inactive_by_customer(): void {
        $org = Organization::factory()->enterprise()->create();
        LicenseFlagOverride::query()->create([
            'organization_id' => $org->id,
            'flag' => 'module.lager',
            'disabled_at' => CarbonImmutable::now(),
        ]);

        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->resolver->statusFor($org, 'module.lager'));
        $this->assertFalse($this->resolver->isActiveFor($org, 'module.lager'));
    }

    public function test_system_override_blocks_licensed_module(): void {
        config(['license.feature_overrides' => ['module.lager' => false]]);
        $org = Organization::factory()->enterprise()->create();

        $this->assertSame(ModuleStatus::Blocked, $this->resolver->statusFor($org, 'module.lager'));
    }

    public function test_disable_is_scoped_per_organization(): void {
        $orgA = Organization::factory()->enterprise()->create();
        $orgB = Organization::factory()->enterprise()->create();
        LicenseFlagOverride::query()->create([
            'organization_id' => $orgA->id,
            'flag' => 'module.chat',
            'disabled_at' => CarbonImmutable::now(),
        ]);

        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->resolver->statusFor($orgA, 'module.chat'));
        $this->assertSame(ModuleStatus::Active, $this->resolver->statusFor($orgB, 'module.chat'));
    }

    public function test_for_organization_returns_full_catalog_with_sources(): void {
        $org = Organization::factory()->enterprise()->create();

        $rows = $this->resolver->forOrganization($org);
        $this->assertNotEmpty($rows);

        $byCode = collect($rows)->keyBy('code');
        $this->assertTrue($byCode->has('module.lager'));
        $this->assertSame('plan', $byCode['module.lager']['source']);
        $this->assertSame(ModuleStatus::Active, $byCode['module.lager']['status']);
    }
}
