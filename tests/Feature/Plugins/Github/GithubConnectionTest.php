<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubConnectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Github;

use App\Models\{Organization, PluginSetting, PluginState};
use App\Plugins\Contracts\{PluginCapability, TaskSyncer};
use App\Plugins\Github\{GithubConfig, GithubPlugin};
use App\Plugins\{PluginDiscovery, PluginHealth};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 060, MVP-129 (Bauturbo A6): Plugin-Verdrahtung + Healthcheck des
 * GitHub-Plugins. Auto-Discovery, TaskSync-Fähigkeit, billige Probe
 * `GET /user` (Pflicht-Header Accept/X-GitHub-Api-Version, Bearer-PAT),
 * Org-Isolation der verschlüsselten Secrets und die org-bezogene
 * Auto-Disable-Zählung über `plugin:healthcheck`.
 */
final class GithubConnectionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function configure(?string $token = 'ghp-token', ?Organization $org = null): PluginSetting {
        return PluginSetting::create([
            'organization_id' => ($org ?? $this->organization)->id,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => $token, 'repo_owner' => 'acme', 'repo_name' => 'support'],
        ]);
    }

    public function test_is_discovered_and_announces_task_sync(): void {
        $this->assertContains(GithubPlugin::class, PluginDiscovery::classes());

        $plugin = new GithubPlugin();
        $this->assertContains(PluginCapability::TaskSync, $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
        $this->assertInstanceOf(TaskSyncer::class, $plugin);
    }

    public function test_healthcheck_without_configuration_is_degraded_not_configured(): void {
        $health = (new GithubPlugin())->healthCheck();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, $health->status);
        $this->assertSame('not_configured', $health->code);
    }

    public function test_empty_encrypted_token_counts_as_not_configured(): void {
        // Leere encrypted-Strings dürfen NIE als Wert durchrutschen (?:-Regel).
        $this->configure(token: '');

        $this->assertNull(GithubConfig::resolve((int) $this->organization->id)['api_token']);
        $this->assertSame('not_configured', (new GithubPlugin())->healthCheck()->code);
    }

    public function test_healthcheck_probes_user_with_required_headers(): void {
        $this->configure();
        $fake = FakePluginHttp::fake([
            'https://api.github.com/user*' => FakePluginHttp::response(['login' => 'octocat']),
        ]);

        $health = (new GithubPlugin())->healthCheck();

        $this->assertSame(PluginHealth::STATUS_OK, $health->status);
        $this->assertStringContainsString('octocat', $health->message);

        // GitHub-Vertrag: Bearer-PAT + vnd.github+json + API-Versions-Header.
        $fake->assertSent(fn($r) => $r->getHeaderLine('Authorization') === 'Bearer ghp-token'
            && $r->getHeaderLine('Accept') === 'application/vnd.github+json'
            && $r->getHeaderLine('X-GitHub-Api-Version') === '2022-11-28');
    }

    public function test_healthcheck_with_rejected_token_is_failing_auth(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://api.github.com/user*' => FakePluginHttp::response(['message' => 'Bad credentials'], 401),
        ]);

        $health = (new GithubPlugin())->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertSame('auth', $health->code);
    }

    public function test_secrets_are_isolated_per_organization(): void {
        $this->configure();
        $other = Organization::factory()->create();
        $this->configure(token: 'ghp-other', org: $other);

        // Mandant B sieht nie den Token von Mandant A — und umgekehrt.
        $this->assertSame('ghp-other', GithubConfig::resolve((int) $other->id)['api_token']);
        $this->assertSame('ghp-token', GithubConfig::resolve((int) $this->organization->id)['api_token']);
    }

    public function test_healthcheck_command_records_failing_state_per_organization(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://api.github.com/user*' => FakePluginHttp::response(['message' => 'Bad credentials'], 401),
        ]);

        $this->artisan('plugin:healthcheck', ['plugin' => GithubPlugin::ID, '--no-fail' => true])
            ->assertSuccessful();

        $state = PluginState::query()
            ->where('plugin_id', GithubPlugin::ID)
            ->where('organization_id', $this->organization->id)
            ->firstOrFail();
        $this->assertSame(PluginHealth::STATUS_FAILING, $state->last_health_status);
        // Fehler zählt org-bezogen auf den Auto-Disable-Zähler ein
        // (Schwellen-Mechanik generisch in PluginManagerAutoDisableTest).
        $this->assertGreaterThanOrEqual(1, (int) $state->failure_count);
    }
}
