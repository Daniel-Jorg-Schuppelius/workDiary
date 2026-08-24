<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabSyncCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Gitlab;

use App\Models\{PluginSetting, Task};
use App\Plugins\Gitlab\GitlabPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: Smoke für gitlab:sync gegen die gefakte
 * HTTP-Schicht (FakePluginHttp) — Import-Details stehen im Importer-Test.
 */
final class GitlabSyncCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function configure(): void {
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'glpat-token', 'project_id' => '42'],
        ]);
    }

    public function test_sync_imports_issues_as_tasks(): void {
        $this->configure();
        $fake = FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/42/issues*' => [
                FakePluginHttp::response([[
                    'iid' => 7,
                    'title' => 'Deployment hakt',
                    'state' => 'opened',
                    'web_url' => 'https://gitlab.com/acme/support/-/issues/7',
                    'updated_at' => now()->toIso8601String(),
                    'labels' => [],
                ]]),
                FakePluginHttp::response([]),
            ],
            'https://gitlab.com/*' => FakePluginHttp::response([]),
        ]);

        $this->artisan('gitlab:sync', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('created 1')
            ->assertExitCode(0);

        $this->assertSame(1, Task::query()->where('organization_id', $this->organization->id)->count());
        $fake->assertSent(fn($r) => str_contains((string) $r->getUri(), '/api/v4/projects/42/issues'));
    }

    public function test_unconfigured_organization_is_skipped(): void {
        FakePluginHttp::fake(); // jeder Request wäre ein Fehler im Skip-Pfad

        $this->artisan('gitlab:sync', ['--organization' => (string) $this->organization->id])
            ->assertExitCode(0);

        $this->assertSame(0, Task::query()->count());
    }
}
