<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealthCheckCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\PluginState;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth, PluginManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginHealthCheckCommandTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $manager = new PluginManager;
        $manager->register(new FakeHealthyPlugin);
        $manager->register(new FakeFailingPlugin);
        $this->app->instance(PluginManager::class, $manager);
    }

    public function test_command_persists_health_and_records_failures(): void {
        $this->artisan('plugin:healthcheck')->assertExitCode(1); // failing plugin -> non-zero

        $healthy = PluginState::query()->where('plugin_id', 'healthy')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_OK, $healthy->last_health_status);

        $failing = PluginState::query()->where('plugin_id', 'broken')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $failing->last_health_status);
        $this->assertSame(1, (int) $failing->failure_count);
    }

    public function test_no_fail_option_exits_zero_but_still_records_state(): void {
        // Geplante Läufe nutzen --no-fail: ungesundes Plugin darf den Command
        // (und damit den Scheduler) NICHT als Fehlschlag markieren.
        $this->artisan('plugin:healthcheck --no-fail')->assertExitCode(0);

        $failing = PluginState::query()->where('plugin_id', 'broken')->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $failing->last_health_status);
        $this->assertSame(1, (int) $failing->failure_count);
    }
}

final class FakeHealthyPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'healthy';
    }
    public function name(): string {
        return 'Healthy';
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
        return [PluginCapability::CONTACT_SYNC];
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
    public function healthCheck(): PluginHealth {
        return PluginHealth::ok('all good');
    }
}

final class FakeFailingPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'broken';
    }
    public function name(): string {
        return 'Broken';
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
        return [PluginCapability::CONTACT_SYNC];
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
    public function healthCheck(): PluginHealth {
        return PluginHealth::failing('api down');
    }
}
