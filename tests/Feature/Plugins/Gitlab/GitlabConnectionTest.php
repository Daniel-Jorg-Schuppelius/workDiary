<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Gitlab;

use App\Models\{Organization, PluginSetting, PluginState};
use App\Plugins\Contracts\{PluginCapability, TaskSyncer};
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\{PluginDiscovery, PluginHealth};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 060, MVP-129 (Bauturbo A6): Plugin-Verdrahtung + Healthcheck des
 * GitLab-Plugins. Auto-Discovery, TaskSync-Fähigkeit, billige Probe
 * `GET /api/v4/user` (PRIVATE-TOKEN), SSRF-Leitplanke der self-hosted
 * Instanz-URL, Org-Isolation der verschlüsselten Secrets und die
 * org-bezogene Auto-Disable-Zählung über `plugin:healthcheck`.
 */
final class GitlabConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param array<string, mixed> $settings */
    private function configure(?string $token = 'glpat-token', ?Organization $org = null, array $settings = []): PluginSetting {
        return PluginSetting::create([
            'organization_id' => ($org ?? $this->organization)->id,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => true,
            'settings' => $settings + [
                'api_token' => $token,
                'project_id' => '4242',
                // Testumgebung: DNS-Auflösung der SSRF-Leitplanke vermeiden.
                'allow_private_network' => true,
            ],
        ]);
    }

    public function test_is_discovered_and_announces_task_sync(): void {
        $this->assertContains(GitlabPlugin::class, PluginDiscovery::classes());

        $plugin = new GitlabPlugin();
        $this->assertContains(PluginCapability::TaskSync, $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
        $this->assertInstanceOf(TaskSyncer::class, $plugin);
    }

    public function test_healthcheck_without_configuration_is_degraded_not_configured(): void {
        $health = (new GitlabPlugin())->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertSame('not_configured', $health->code);
    }

    public function test_empty_encrypted_token_counts_as_not_configured(): void {
        // Leere encrypted-Strings dürfen NIE als Wert durchrutschen (?:-Regel).
        $this->configure(token: '');

        $this->assertNull(GitlabConfig::resolve((int) $this->organization->id)['api_token']);
        $this->assertSame('not_configured', (new GitlabPlugin())->healthCheck()->code);
    }

    public function test_healthcheck_probes_user_with_private_token_header(): void {
        $this->configure();
        $fake = FakePluginHttp::fake([
            'https://gitlab.com/api/v4/user*' => FakePluginHttp::response(['username' => 'gitlab-bot']),
        ]);

        $health = (new GitlabPlugin())->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
        $this->assertStringContainsString('gitlab-bot', $health->message);
        $fake->assertSent(fn($r) => $r->getHeaderLine('PRIVATE-TOKEN') === 'glpat-token');
    }

    public function test_healthcheck_with_rejected_token_is_failing_auth(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://gitlab.com/api/v4/user*' => FakePluginHttp::response(['message' => '401 Unauthorized'], 401),
        ]);

        $health = (new GitlabPlugin())->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertSame('auth', $health->code);
    }

    public function test_private_instance_url_requires_explicit_allowance(): void {
        // IP-Literal im privaten Bereich → ohne Freigabe blockiert (SSRF-Leitplanke).
        $this->configure(settings: ['base_url' => 'http://10.0.0.5', 'allow_private_network' => false]);
        $fake = FakePluginHttp::fake([
            'http://10.0.0.5/api/v4/user*' => FakePluginHttp::response(['username' => 'intern']),
        ]);

        $health = (new GitlabPlugin())->healthCheck();
        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $fake->assertNothingSent();

        // Mit ausdrücklicher Freigabe (On-Premise im eigenen Netz) erreichbar.
        PluginSetting::query()->delete();
        $this->configure(settings: ['base_url' => 'http://10.0.0.5', 'allow_private_network' => true]);
        $this->assertSame(PluginHealth::STATUS_OK, (new GitlabPlugin())->healthCheck()->status);
    }

    public function test_secrets_are_isolated_per_organization(): void {
        $this->configure();
        $other = Organization::factory()->create();
        $this->configure(token: 'glpat-other', org: $other);

        // Mandant B sieht nie den Token von Mandant A — und umgekehrt.
        $this->assertSame('glpat-other', GitlabConfig::resolve((int) $other->id)['api_token']);
        $this->assertSame('glpat-token', GitlabConfig::resolve((int) $this->organization->id)['api_token']);
    }

    public function test_healthcheck_command_records_failing_state_per_organization(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://gitlab.com/api/v4/user*' => FakePluginHttp::response(['message' => '401 Unauthorized'], 401),
        ]);

        $this->artisan('plugin:healthcheck', ['plugin' => GitlabPlugin::ID, '--no-fail' => true])
            ->assertSuccessful();

        $state = PluginState::query()
            ->where('plugin_id', GitlabPlugin::ID)
            ->where('organization_id', $this->organization->id)
            ->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $state->last_health_status);
        // Fehler zählt org-bezogen auf den Auto-Disable-Zähler ein
        // (Schwellen-Mechanik generisch in PluginManagerAutoDisableTest).
        $this->assertGreaterThanOrEqual(1, (int) $state->failure_count);
    }
}
