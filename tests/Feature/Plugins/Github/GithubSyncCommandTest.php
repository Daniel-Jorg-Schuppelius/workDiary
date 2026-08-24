<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubSyncCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Github;

use App\Models\{PluginSetting, Task};
use App\Plugins\Github\GithubPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: Smoke für github:sync gegen die gefakte
 * HTTP-Schicht (FakePluginHttp) — Import-Details stehen im Importer-Test.
 */
final class GithubSyncCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function configure(): void {
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'ghp-token', 'repo_owner' => 'acme', 'repo_name' => 'support'],
        ]);
    }

    public function test_sync_imports_issues_as_tasks(): void {
        $this->configure();
        // Sequenz: Seite 1 liefert ein Issue, danach ist der Aufholpunkt leer.
        $fake = FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => [
                FakePluginHttp::response([[
                    'id' => 4711,
                    'number' => 12,
                    'title' => 'Drucker brennt',
                    'state' => 'open',
                    'html_url' => 'https://github.com/acme/support/issues/12',
                    'updated_at' => now()->toIso8601String(),
                    'body' => 'Bitte löschen.',
                    'labels' => [],
                ]]),
                FakePluginHttp::response([]),
            ],
            'https://api.github.com/*' => FakePluginHttp::response([]),
        ]);

        $this->artisan('github:sync', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('created 1')
            ->assertExitCode(0);

        $this->assertSame(1, Task::query()->where('organization_id', $this->organization->id)->count());
        $fake->assertSent(fn($r) => str_contains((string) $r->getUri(), '/repos/acme/support/issues'));
    }

    public function test_unconfigured_organization_is_skipped(): void {
        FakePluginHttp::fake(); // jeder Request wäre ein Fehler im Skip-Pfad

        $this->artisan('github:sync', ['--organization' => (string) $this->organization->id])
            ->assertExitCode(0);

        $this->assertSame(0, Task::query()->count());
    }
}
