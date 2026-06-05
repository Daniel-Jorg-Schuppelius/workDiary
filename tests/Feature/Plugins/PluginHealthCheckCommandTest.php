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

use App\Events\{PluginHealthChanged, PluginRecovered};
use App\Models\PluginState;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth, PluginManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_status_transition_dispatches_events_and_sets_last_ok_at(): void {
        Event::fake([PluginHealthChanged::class, PluginRecovered::class]);

        $this->artisan('plugin:healthcheck --no-fail')->assertExitCode(0);

        // Erster Lauf: null → ok/failing zählt als Übergang für beide Plugins.
        Event::assertDispatched(PluginHealthChanged::class, fn($e) => $e->pluginId === 'healthy' && $e->to === PluginHealth::STATUS_OK);
        Event::assertDispatched(PluginHealthChanged::class, fn($e) => $e->pluginId === 'broken' && $e->to === PluginHealth::STATUS_FAILING);
        // Recovery feuert NICHT beim Erststatus (from === null).
        Event::assertNotDispatched(PluginRecovered::class);

        $healthy = PluginState::query()->where('plugin_id', 'healthy')->firstOrFail();
        $this->assertNotNull($healthy->last_ok_at);
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
    public function healthCheck(): PluginHealth {
        return PluginHealth::failing('api down');
    }
}
