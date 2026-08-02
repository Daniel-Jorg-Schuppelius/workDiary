<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealthServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Events\{PluginHealthChanged, PluginRecovered};
use App\Models\PluginState;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth, PluginHealthService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Zentrale Health-Pipeline (Review 2026-08, W3a): Persistenz inkl.
 * Latenz/Code, Hysterese gegen Flapping, Warning-Betriebsaufgabe beim
 * stabilen failing-Übergang (E-4).
 */
class PluginHealthServiceTest extends TestCase {
    use RefreshDatabase;

    private SwitchableHealthPlugin $plugin;

    protected function setUp(): void {
        parent::setUp();
        $this->plugin = new SwitchableHealthPlugin;
    }

    private function service(): PluginHealthService {
        return app(PluginHealthService::class);
    }

    public function test_persists_latency_and_code(): void {
        $this->plugin->result = PluginHealth::degraded('teilweise', code: 'partial');

        $this->service()->check($this->plugin, null);

        $state = PluginState::query()->where('plugin_id', 'switchable')->firstOrFail();
        $this->assertSame('degraded', $state->last_health_status);
        $this->assertSame('partial', $state->last_health_code);
        $this->assertNotNull($state->last_health_latency_ms);
    }

    /** Hysterese (Flap-Threshold 2): ein einzelner Ausrutscher meldet nichts. */
    public function test_status_transition_needs_streak_before_announcing(): void {
        config()->set('plugins.health_flap_threshold', 2);
        $service = $this->service();

        // Erststatus: sofort gemeldet.
        Event::fake([PluginHealthChanged::class, PluginRecovered::class]);
        $this->plugin->result = PluginHealth::ok('gesund');
        $service->check($this->plugin, null);
        Event::assertDispatchedTimes(PluginHealthChanged::class, 1);

        // Erster failing: Streak 1 < 2 → KEINE Meldung.
        $this->plugin->result = PluginHealth::failing('kaputt');
        $service->check($this->plugin, null);
        Event::assertDispatchedTimes(PluginHealthChanged::class, 1);

        // Zweiter failing in Folge: stabiler Übergang → Meldung.
        $service->check($this->plugin, null);
        Event::assertDispatchedTimes(PluginHealthChanged::class, 2);
        Event::assertDispatched(PluginHealthChanged::class, fn($e) => $e->to === PluginHealth::STATUS_FAILING);

        // Roh-Status in der UI zeigt trotzdem sofort das letzte Ergebnis.
        $state = PluginState::query()->where('plugin_id', 'switchable')->firstOrFail();
        $this->assertSame('failing', $state->last_health_status);
        $this->assertSame('failing', $state->last_announced_status);
    }

    public function test_flapping_never_announces(): void {
        config()->set('plugins.health_flap_threshold', 2);
        $service = $this->service();

        $this->plugin->result = PluginHealth::ok();
        $service->check($this->plugin, null);

        Event::fake([PluginHealthChanged::class]);
        foreach ([PluginHealth::failing('x'), PluginHealth::ok(), PluginHealth::failing('x'), PluginHealth::ok()] as $result) {
            $this->plugin->result = $result;
            $service->check($this->plugin, null);
        }

        Event::assertNotDispatched(PluginHealthChanged::class);
    }

    /** E-4/W3d: stabiler failing-Übergang erzeugt eine Warning-Betriebsaufgabe; Recovery löst sie auf. */
    public function test_stable_failing_creates_warning_task_and_recovery_resolves(): void {
        // Betriebsaufgaben brauchen eine Organisation (vgl. OperationsTaskCenterTest).
        $organization = \App\Models\Organization::factory()->create();
        app()->instance('currentOrganization', $organization);

        config()->set('plugins.health_flap_threshold', 1);
        $service = $this->service();
        $orgId = (int) $organization->id;

        $this->plugin->result = PluginHealth::ok();
        $service->check($this->plugin, $orgId);

        $this->plugin->result = PluginHealth::failing('api down');
        $service->check($this->plugin, $orgId);

        $task = \App\Models\OperationsTask::query()
            ->where('dedupe_key', 'plugin_failing:switchable:' . $orgId)
            ->first();
        $this->assertNotNull($task, 'failing-Übergang muss eine Betriebsaufgabe erzeugen.');
        $this->assertSame(\App\Enums\Operations\OperationsTaskSeverity::Warning, $task->severity);

        $this->plugin->result = PluginHealth::ok('wieder da');
        $service->check($this->plugin, $orgId);

        $this->assertNotNull($task->refresh()->resolved_at, 'Recovery muss die Aufgabe auflösen.');
    }
}

final class SwitchableHealthPlugin implements Plugin {
    use PluginDefaults;

    public PluginHealth $result;

    public function __construct() {
        $this->result = PluginHealth::ok();
    }

    public function id(): string {
        return 'switchable';
    }
    public function name(): string {
        return 'Switchable';
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
        return $this->result;
    }
}
