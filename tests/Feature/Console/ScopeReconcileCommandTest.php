<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeReconcileCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\Licensing\ModuleStatus;
use App\Models\Platform\{LicenseFlagOverride, Organization, User};
use App\Services\Licensing\{ModuleScopeService, ModuleStatusResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeReconcileCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_command_disables_unchosen_modules_of_limited_organizations_only(): void {
        $limited = Organization::factory()->enterprise()->create(['name' => 'Begrenzt']);
        $full = Organization::factory()->enterprise()->create(['name' => 'Voll']);
        $admin = User::factory()->admin()->create(['organization_id' => $limited->id]);
        app()->instance('currentOrganization', $limited);
        $scope = app(ModuleScopeService::class);
        $scope->applyPreset($limited, 'start', $admin);

        // Ein „neues Katalogmodul" simulieren: Override entfernen, als wäre das
        // Modul erst nach der Wahl lizenziert worden.
        LicenseFlagOverride::query()->where('organization_id', $limited->id)->where('flag', 'module.lager')->delete();
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($limited->fresh(), 'module.lager'));

        $this->artisan('scope:reconcile')
            ->expectsOutputToContain('module.lager')
            ->expectsOutputToContain('1 begrenzte Organisation(en) geprüft, 1 Modul(e) neu deaktiviert')
            ->assertExitCode(0);

        $this->assertSame(ModuleStatus::InactiveByCustomer, app(ModuleStatusResolver::class)->statusFor($limited->fresh(), 'module.lager'));
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($full->fresh(), 'module.lager'));

        $this->artisan('scope:reconcile', ['--org' => $limited->id])
            ->expectsOutputToContain('1 begrenzte Organisation(en) geprüft, 0 Modul(e) neu deaktiviert')
            ->assertExitCode(0);
    }
}
