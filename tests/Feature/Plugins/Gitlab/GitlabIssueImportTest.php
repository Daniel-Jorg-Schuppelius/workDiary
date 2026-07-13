<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabIssueImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Gitlab;

use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, PluginSetting, Task};
use App\Plugins\Gitlab\Api\GitlabClientFactory;
use App\Plugins\Gitlab\GitlabPlugin;
use App\Plugins\Gitlab\Services\GitlabIssueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 060, MVP-129 (Bauturbo A6): GitLab-Issue-Import als Aufgaben.
 * Idempotenz über ExternalReference mit dem zusammengesetzten Schlüssel
 * `project_id#iid` (NIE die globale id — Recherche-Falle), Status-Mapping
 * opened/closed inkl. Wieder-Öffnen, `updated_after`-Aufholpunkt und
 * per_page ≤ 100. HTTP ausschließlich über den Guzzle-MockHandler
 * (FakePluginHttp) — nie gegen die echte API.
 */
final class GitlabIssueImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const PROJECT_ID = '4242';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param array<string, mixed> $settings */
    private function configure(array $settings = []): PluginSetting {
        return PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => true,
            'settings' => $settings + [
                'api_token' => 'glpat-token',
                'project_id' => self::PROJECT_ID,
                // Testumgebung: DNS-Auflösung der SSRF-Leitplanke vermeiden.
                'allow_private_network' => true,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function issue(int $iid, string $state = 'opened', array $extra = []): array {
        return $extra + [
            // Globale id ABSICHTLICH völlig anders als iid — sie darf nie in
            // den Referenz-Schlüssel einfließen.
            'id' => 990000 + $iid,
            'iid' => $iid,
            'project_id' => (int) self::PROJECT_ID,
            'title' => "Issue {$iid}",
            'state' => $state,
            'updated_at' => sprintf('2026-07-12T10:%02d:00Z', min(59, $iid)),
        ];
    }

    /** @return array{created: int, updated: int, skipped: int, inbox: int} */
    private function import(): array {
        return app(GitlabIssueImporter::class)->import(
            $this->organization,
            app(GitlabClientFactory::class)->for((int) $this->organization->id),
        );
    }

    public function test_imports_issues_with_iid_plus_project_id_key(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/4242/issues*' => FakePluginHttp::response([
                $this->issue(1),
                $this->issue(2, 'closed'),
            ]),
        ]);

        $first = $this->import();

        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0, 'inbox' => 0], $first);
        $this->assertSame(2, Task::query()->count());

        // Schlüssel = project_id#iid — die globale id (990001) taucht nirgends auf.
        $this->assertNotNull(ExternalReference::query()
            ->where('plugin_id', GitlabPlugin::ID)
            ->where('external_type', GitlabPlugin::EXT_TYPE_ISSUE)
            ->where('external_id', self::PROJECT_ID . '#1')
            ->first());
        $this->assertSame(0, ExternalReference::query()
            ->where('plugin_id', GitlabPlugin::ID)
            ->where('external_id', 'like', '%990001%')
            ->count());

        // Replay: keine Dubletten.
        $second = $this->import();
        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 2, 'inbox' => 0], $second);
        $this->assertSame(2, Task::query()->count());
    }

    public function test_status_mapping_closed_becomes_done_and_reopen_reopens(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/4242/issues*' => [
                FakePluginHttp::response([$this->issue(5, 'closed')]),
                FakePluginHttp::response([$this->issue(5, 'opened', ['updated_at' => '2026-07-12T11:00:00Z'])]),
            ],
        ]);

        $this->import();
        $task = Task::query()->firstOrFail();
        $this->assertSame(TaskStatus::Done, $task->status);

        // Wieder-Öffnen im Ticketsystem öffnet die erledigte Aufgabe erneut.
        $second = $this->import();
        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0, 'inbox' => 0], $second);
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
        $this->assertSame(1, Task::query()->count());
    }

    public function test_updated_after_checkpoint_advances_and_is_sent_on_next_poll(): void {
        $row = $this->configure();
        $fake = FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/4242/issues*' => FakePluginHttp::response([
                $this->issue(1, 'opened', ['updated_at' => '2026-07-12T09:15:00Z']),
                $this->issue(2, 'opened', ['updated_at' => '2026-07-12T10:30:00Z']),
            ]),
        ]);

        $this->import();

        $this->assertSame('2026-07-12T10:30:00Z', $row->refresh()->get(GitlabIssueImporter::CHECKPOINT_KEY));

        // Folgelauf: updated_after=<Aufholpunkt> und per_page ≤ 100 (GitLab-Limit).
        $this->import();
        $fake->assertSent(fn($r) => str_contains((string) $r->getUri(), 'updated_after=' . rawurlencode('2026-07-12T10:30:00Z'))
            && str_contains((string) $r->getUri(), 'per_page=100'));
    }

    public function test_self_hosted_base_url_is_used(): void {
        $this->configure(['base_url' => 'https://gitlab.example.com']);
        $fake = FakePluginHttp::fake([
            'https://gitlab.example.com/api/v4/projects/4242/issues*' => FakePluginHttp::response([$this->issue(1)]),
        ]);

        $result = $this->import();

        $this->assertSame(1, $result['created']);
        // GitLab-Vertrag: Auth über den PRIVATE-TOKEN-Header.
        $fake->assertSent(fn($r) => $r->getHeaderLine('PRIVATE-TOKEN') === 'glpat-token'
            && str_starts_with((string) $r->getUri(), 'https://gitlab.example.com/'));
    }

    public function test_sync_tasks_aggregates_counters_via_task_syncer_contract(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/4242/issues*' => FakePluginHttp::response([$this->issue(1), $this->issue(2)]),
        ]);

        $first = (new GitlabPlugin())->syncTasks($this->organization);
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['failed']);

        $second = (new GitlabPlugin())->syncTasks($this->organization);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['unchanged']);
    }
}
