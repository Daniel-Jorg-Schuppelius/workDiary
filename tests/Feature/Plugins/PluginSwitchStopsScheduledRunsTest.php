<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSwitchStopsScheduledRunsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\Project\Task;
use App\Plugins\Calendly\CalendlyPlugin;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use App\Plugins\Zammad\Contracts\{ZammadGateway, ZammadGatewayFactory};
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\ZammadPlugin;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\TestCase;

/**
 * Schaltet eine Organisation ein Plugin ab, stehen seine geplanten Läufe —
 * auch wenn die Verbindung noch besteht (Konsolidierungs-Audit 2026-10, k2-06).
 */
final class PluginSwitchStopsScheduledRunsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_switched_off_plugin_stands_despite_an_active_connection(): void {
        ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Helpdesk',
            'base_url' => 'https://zammad.example.org',
            'api_token' => 'secret',
            'active' => true,
        ]);
        $called = false;
        $this->app->instance(ZammadGatewayFactory::class, new class($called) implements ZammadGatewayFactory {
            public function __construct(private bool &$called) {}

            public function for(ZammadConnection $connection): ZammadGateway {
                $this->called = true;

                throw new \LogicException('Der Lauf darf die Gegenseite nicht ansprechen.');
            }
        });
        $this->pluginSecret(ZammadPlugin::ID, [])->forceFill(['enabled' => false])->save();

        $this->artisan('zammad:sync', ['--organization' => (string) $this->organization->id])->assertExitCode(0);

        $this->assertFalse($called);
        $this->assertSame(0, Task::query()->count());
    }

    /** Der Schalter der Organisation gewinnt gegen die Vorgabe der Installation — in beide Richtungen. */
    public function test_organization_switch_wins_over_the_installation_default(): void {
        $organizationId = (int) $this->organization->id;

        $this->assertFalse($this->switchFor(CalendlyPlugin::ID, $organizationId));

        config()->set('plugins.calendly.enabled', true);
        $this->assertTrue($this->switchFor(CalendlyPlugin::ID, $organizationId));

        $this->pluginSecret(CalendlyPlugin::ID, [])->forceFill(['enabled' => false])->save();
        $this->assertFalse($this->switchFor(CalendlyPlugin::ID, $organizationId));
    }

    /** Entscheidung 2026-10-05: für einen gesperrten Mandanten steht jeder geplante Plugin-Lauf. */
    public function test_blocked_tenant_stops_scheduled_runs(): void {
        $organizationId = (int) $this->organization->id;
        config()->set('plugins.calendly.enabled', true);
        $this->assertTrue($this->switchFor(CalendlyPlugin::ID, $organizationId));

        $this->organization->forceFill(['is_active' => false])->save();
        $this->assertFalse($this->switchFor(CalendlyPlugin::ID, $organizationId));

        $this->organization->forceFill(['is_active' => true])->save();
        $this->assertTrue($this->switchFor(CalendlyPlugin::ID, $organizationId));
    }

    /** Je Aufruf ein neues Kommando — die Prüfung merkt sich ihr Ergebnis für den Lauf. */
    private function switchFor(string $pluginId, int $organizationId): bool {
        $command = new class extends Command {
            use ChecksPluginSwitch;

            protected $name = 'test:plugin-switch';

            public function enabledFor(string $pluginId, int $organizationId): bool {
                return $this->pluginEnabledFor($pluginId, $organizationId);
            }
        };

        return $command->enabledFor($pluginId, $organizationId);
    }
}
