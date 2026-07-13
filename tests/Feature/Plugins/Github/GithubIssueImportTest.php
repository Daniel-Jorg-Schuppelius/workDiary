<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubIssueImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Github;

use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, Organization, PluginSetting, Project, Task};
use App\Plugins\Github\Api\GithubClientFactory;
use App\Plugins\Github\GithubPlugin;
use App\Plugins\Github\Services\GithubIssueImporter;
use App\Services\SqidEncoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 060, MVP-129 (Bauturbo A6): GitHub-Issue-Import als Aufgaben.
 * Idempotenz über ExternalReference (`owner/repo#number`), der Pflicht-Filter
 * für Pull Requests (die Issues-API liefert beide!), Status-Mapping inkl.
 * Wieder-Öffnen, `since`-Aufholpunkt und die Mandantengrenze des
 * Standard-Projekts. HTTP ausschließlich über den Guzzle-MockHandler
 * (FakePluginHttp) — nie gegen die echte API.
 */
final class GithubIssueImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param array<string, mixed> $settings */
    private function configure(array $settings = [], ?Organization $org = null): PluginSetting {
        return PluginSetting::create([
            'organization_id' => ($org ?? $this->organization)->id,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => true,
            'settings' => $settings + ['api_token' => 'ghp-token', 'repo_owner' => 'acme', 'repo_name' => 'support'],
        ]);
    }

    /** @return array<string, mixed> */
    private function issue(int $number, string $state = 'open', array $extra = []): array {
        return $extra + [
            'id' => 900000 + $number,
            'number' => $number,
            'title' => "Issue {$number}",
            'state' => $state,
            'updated_at' => sprintf('2026-07-12T10:%02d:00Z', min(59, $number)),
        ];
    }

    /** @return array{created: int, updated: int, skipped: int, inbox: int} */
    private function import(): array {
        return app(GithubIssueImporter::class)->import(
            $this->organization,
            app(GithubClientFactory::class)->for((int) $this->organization->id),
        );
    }

    public function test_imports_issues_idempotently_and_filters_pull_requests(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => FakePluginHttp::response([
                $this->issue(1),
                $this->issue(2, 'closed'),
                // Die Issues-API liefert auch Pull Requests — MUSS gefiltert werden.
                $this->issue(3, 'open', ['pull_request' => ['url' => 'https://api.github.com/repos/acme/support/pulls/3']]),
            ]),
        ]);

        $first = $this->import();

        $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0, 'inbox' => 0], $first);
        $this->assertSame(2, Task::query()->count());
        $this->assertSame(0, Task::query()->where('title', 'like', '%Issue 3%')->count());

        $this->assertNotNull(ExternalReference::query()
            ->where('plugin_id', GithubPlugin::ID)
            ->where('external_type', GithubPlugin::EXT_TYPE_ISSUE)
            ->where('external_id', 'acme/support#1')
            ->first());

        // Replay: keine Dubletten, unveränderte Issues werden übersprungen.
        $second = $this->import();
        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 2, 'inbox' => 0], $second);
        $this->assertSame(2, Task::query()->count());
    }

    public function test_status_mapping_closed_and_not_planned_become_done(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => FakePluginHttp::response([
                $this->issue(1, 'closed', ['state_reason' => 'completed']),
                $this->issue(2, 'closed', ['state_reason' => 'not_planned']),
                $this->issue(3, 'open'),
            ]),
        ]);

        $this->import();

        $this->assertSame(2, Task::query()->where('status', TaskStatus::Done->value)->count());
        $this->assertSame(1, Task::query()->where('status', TaskStatus::Open->value)->count());
    }

    public function test_reopened_issue_reopens_done_task(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => [
                FakePluginHttp::response([$this->issue(7, 'closed')]),
                FakePluginHttp::response([$this->issue(7, 'open', ['updated_at' => '2026-07-12T11:00:00Z'])]),
            ],
        ]);

        $this->import();
        $task = Task::query()->firstOrFail();
        $this->assertSame(TaskStatus::Done, $task->status);

        $second = $this->import();
        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0, 'inbox' => 0], $second);
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
        $this->assertSame(1, Task::query()->count());
    }

    public function test_since_checkpoint_advances_and_is_sent_on_next_poll(): void {
        $row = $this->configure();
        $fake = FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => FakePluginHttp::response([
                $this->issue(1, 'open', ['updated_at' => '2026-07-12T09:15:00Z']),
                $this->issue(2, 'open', ['updated_at' => '2026-07-12T10:30:00Z']),
            ]),
        ]);

        $this->import();

        // Aufholpunkt = größtes gesehenes updated_at (serverseitige Uhr).
        $this->assertSame('2026-07-12T10:30:00Z', $row->refresh()->get(GithubIssueImporter::CHECKPOINT_KEY));

        // Der Folgelauf fragt mit since=<Aufholpunkt> an.
        $this->import();
        $fake->assertSent(fn($r) => str_contains((string) $r->getUri(), 'since=' . rawurlencode('2026-07-12T10:30:00Z')));
    }

    public function test_default_project_respects_tenant_boundary(): void {
        $sqids = app(SqidEncoder::class);
        $own = Project::factory()->create(['organization_id' => $this->organization->id]);
        $foreignOrg = Organization::factory()->create();
        $foreign = Project::factory()->create(['organization_id' => $foreignOrg->id]);

        // Fremdes Projekt darf NIE zugeordnet werden → globale Aufgabe.
        $this->configure(['default_project' => $sqids->encode(Project::class, (int) $foreign->id)]);
        FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => FakePluginHttp::response([$this->issue(1)]),
        ]);
        $this->import();

        $task = Task::query()->firstOrFail();
        $this->assertNull($task->project_id);
        $this->assertTrue($task->is_global);

        // Eigenes Projekt wird zugeordnet.
        PluginSetting::query()->delete();
        ExternalReference::query()->delete();
        $this->configure(['default_project' => $sqids->encode(Project::class, (int) $own->id)]);
        $this->import();

        $mapped = Task::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame((int) $own->id, (int) $mapped->project_id);
        $this->assertFalse($mapped->is_global);
    }

    public function test_sync_tasks_aggregates_counters_via_task_syncer_contract(): void {
        $this->configure();
        FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => FakePluginHttp::response([$this->issue(1), $this->issue(2)]),
        ]);

        $first = (new GithubPlugin())->syncTasks($this->organization);
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['failed']);

        $second = (new GithubPlugin())->syncTasks($this->organization);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['unchanged']);
    }
}
