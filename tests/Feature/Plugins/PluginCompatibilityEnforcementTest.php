<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCompatibilityEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{PluginSetting, PluginState, User};
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth, PluginManager};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Durchsetzung der Plugin-Kompatibilitätsangaben (Feature 022):
 * inkompatibles Plugin wird im Healthcheck als failing geführt und kann
 * nicht aktiviert werden.
 */
class PluginCompatibilityEnforcementTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('app.version', '1.0.0');
        $manager = new PluginManager;
        $manager->register(new FakeIncompatiblePlugin);
        $this->app->instance(PluginManager::class, $manager);
    }

    public function test_healthcheck_marks_incompatible_plugin_failing(): void {
        $this->artisan('plugin:healthcheck')->assertExitCode(1);

        $state = PluginState::query()->where('plugin_id', 'incompatible')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $state->last_health_status);
        $this->assertSame(1, (int) $state->failure_count);
        $this->assertStringContainsString('2.0.0', (string) $state->last_health_message);
    }

    public function test_admin_cannot_activate_incompatible_plugin(): void {
        $this->seed(PermissionsSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plugins.toggle', 'incompatible'))
            ->assertRedirect();

        $row = PluginSetting::query()->withoutGlobalScopes()
            ->where('plugin_id', 'incompatible')->first();

        // Aktivierung blockiert → kein enabled-Eintrag bzw. enabled = false.
        $this->assertTrue($row === null || ! (bool) $row->enabled);
    }
}

final class FakeIncompatiblePlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'incompatible';
    }
    public function name(): string {
        return 'Incompatible';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
    public function minAppVersion(): ?string {
        return '2.0.0'; // verlangt Kern >= 2.0.0, läuft aber auf 1.0.0
    }
    public function healthCheck(): PluginHealth {
        return PluginHealth::ok();
    }
}
