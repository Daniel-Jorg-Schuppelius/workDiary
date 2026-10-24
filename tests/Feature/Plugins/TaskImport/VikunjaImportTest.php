<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Integration\TaskImportLinkStatus;
use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Plugins\Vikunja\Models\{VikunjaConnection, VikunjaProjectLink};
use App\Plugins\Vikunja\Services\VikunjaImportService;
use App\Plugins\Vikunja\VikunjaPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Vikunja ab 1.0 (MVP-1340): Bearer-Token aus den Plugin-Einstellungen,
 * Aufgaben über `GET /tasks` mit Filter `project = …` und `page`/`per_page`
 * bis `x-pagination-total-pages`, Erstlauf zusätzlich `done = false`,
 * Folgeläufe mit lokalem `updated`-Filter, HTML der Beschreibung als Text,
 * Nullzeitpunkt als „keine Fälligkeit“, Kontoname nur mit Token-Recht.
 */
class VikunjaImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://todo.example.test';

    private const PROJECT = '12';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(VikunjaPlugin::ID, ['base_url' => self::BASE . '/', 'api_token' => 'tk_vikunja']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): VikunjaConnection {
        return VikunjaConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'todo.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): VikunjaProjectLink {
        return VikunjaProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::PROJECT,
            'source_project_key' => 'WEB',
            'source_project_name' => 'Website',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(int $id, string $title, bool $done = false, array $overrides = []): array {
        return $overrides + [
            'id' => $id,
            'title' => $title,
            'description' => '<p>Text zu <strong>' . $title . '</strong></p>',
            'done' => $done,
            'due_date' => '0001-01-01T00:00:00Z',
            'project_id' => (int) self::PROJECT,
            'identifier' => 'WEB-' . $id,
            'index' => $id,
            'updated' => now()->utc()->toRfc3339String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @param  array<string, mixed>  $extra
     */
    private function serve(array $tasks, array $extra = []): FakePluginHttp {
        $byId = [];
        foreach ($tasks as $task) {
            $byId[self::BASE . '/api/v1/tasks/' . $task['id']] = FakePluginHttp::response($task);
        }

        return FakePluginHttp::fake($extra + $byId + [
            self::BASE . '/api/v1/tasks?*' => FakePluginHttp::response($tasks, 200, ['x-pagination-total-pages' => '1']),
            self::BASE . '/api/v1/projects*' => FakePluginHttp::response([
                ['id' => -1, 'title' => 'Favoriten'],
                ['id' => 12, 'title' => 'Website', 'identifier' => 'WEB'],
                ['id' => 13, 'title' => 'Intern', 'identifier' => ''],
            ], 200, ['x-pagination-total-pages' => '1']),
            self::BASE . '/api/v1/tasks/*' => FakePluginHttp::response(['message' => 'not found'], 404),
            self::BASE . '/api/v1/user' => FakePluginHttp::response(['id' => 3, 'username' => 'erika', 'name' => 'Erika Muster']),
            self::BASE . '/api/v1/info' => FakePluginHttp::response(['version' => 'v2.7.1']),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(VikunjaImportService::class)->run($this->organization, $full);
    }

    private function task(int $remoteId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, VikunjaPlugin::ID, VikunjaPlugin::EXT_TYPE_TASK)
            ->forExternalId(SelfHostedInstance::key(self::BASE) . ':' . $remoteId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_verify_records_the_instance_account_and_version(): void {
        $http = $this->serve([]);
        $this->actingAs($this->admin)->get(route('admin.vikunja.index'))->assertOk()
            ->assertSee(route('admin.vikunja.verify'), false)
            ->assertDontSee('oauth');

        $this->post(route('admin.vikunja.verify'))->assertSessionHas('success');

        $connection = VikunjaConnection::query()->sole();
        $this->assertSame(SelfHostedInstance::key(self::BASE), $connection->source_id);
        $this->assertSame('Erika Muster', $connection->account_name);
        $this->assertStringContainsString('v2.7.1', (string) $connection->source_name);
        $http->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('Authorization') === 'Bearer tk_vikunja');
    }

    public function test_token_without_user_right_still_connects_and_old_versions_are_named(): void {
        $this->serve([], [self::BASE . '/api/v1/user' => FakePluginHttp::response(['code' => 11, 'message' => 'missing permission'], 401)]);
        $this->actingAs($this->admin)->post(route('admin.vikunja.verify'))->assertSessionHas('success');
        $this->assertNull(VikunjaConnection::query()->sole()->account_name, 'Ohne „other → user“ bleibt nur der Kontoname leer.');

        $this->serve([], [self::BASE . '/api/v1/info' => FakePluginHttp::response(['version' => 'v0.24.6'])]);
        $this->post(route('admin.vikunja.verify'))->assertSessionHas('error');
        $this->assertStringContainsString('VikunjaVersionUnsupportedException', (string) session('error'));
    }

    public function test_first_run_reads_open_tasks_over_all_pages_and_maps_the_fields(): void {
        $this->connect();
        $this->link();
        $pages = [
            1 => [
                $this->item(501, 'Startseite', false, ['due_date' => '2026-11-03T23:30:00Z']),
                $this->item(502, 'Fremdes Projekt', false, ['project_id' => 99]),
            ],
            2 => [$this->item(503, 'Impressum')],
        ];
        $http = FakePluginHttp::fake([
            self::BASE . '/api/v1/tasks?*' => function (RequestInterface $request) use ($pages) {
                parse_str($request->getUri()->getQuery(), $query);

                // Die Instanz kappt `per_page` auf 2 — das Ende ergibt sich aus der Seitenzahl.
                return FakePluginHttp::response($pages[(int) ($query['page'] ?? 1)] ?? [], 200, ['x-pagination-total-pages' => '2']);
            },
        ]);

        $counters = $this->sync();

        $this->assertSame(2, $counters['created']);
        $task = $this->task(501);
        $this->assertSame('Startseite', $task?->title);
        $this->assertSame('Text zu Startseite', $task?->description);
        $this->assertSame('2026-11-04', $task?->due_date?->toDateString(), 'Fälligkeit in der Zeitzone der Organisation.');
        $this->assertNull($this->task(503)?->due_date, 'Der Nullzeitpunkt bedeutet keine Fälligkeit.');
        $this->assertNull($this->task(502), 'Aufgaben anderer Projekte bleiben außen vor.');
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return $request->getUri()->getPath() === '/api/v1/tasks' && ($query['page'] ?? null) === '2'
                && ($query['filter'] ?? null) === 'project = 12 && done = false' && ($query['sort_by'] ?? null) === 'id';
        });
        $this->assertTrue(VikunjaProjectLink::query()->sole()->is_last_run_complete);
    }

    public function test_later_runs_filter_only_the_project_and_compare_only_changed_tasks(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->item(501, 'A'), $this->item(505, 'B')]);
        $this->sync();
        $this->task(505)?->forceFill(['status' => TaskStatus::InProgress])->save();
        $old = now()->subDay()->utc()->toRfc3339String();

        $this->travel(20)->minutes();
        $http = $this->serve([
            $this->item(501, 'A', true),
            $this->item(505, 'B'),
            $this->item(506, 'Alt, offen', false, ['updated' => $old]),
        ]);
        $counters = $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(501)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(505)?->status);
        $this->assertNull($this->task(506), 'Vor dem Aufholpunkt geänderte Aufgaben vergleicht erst der Volllauf.');
        $this->assertSame(0, $counters['failed']);
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return $request->getUri()->getPath() === '/api/v1/tasks' && ($query['filter'] ?? null) === 'project = 12';
        });
    }

    public function test_a_page_without_the_page_count_header_is_incomplete(): void {
        $this->connect();
        $link = $this->link();
        $this->serve([], [self::BASE . '/api/v1/tasks?*' => FakePluginHttp::response([$this->item(501, 'A')])]);

        $this->sync();

        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete, 'Ohne x-pagination-total-pages ließe sich ein gekapptes per_page nicht erkennen.');
        $this->assertNull($link->fresh()?->checkpoint_at);
    }

    public function test_attach_reads_the_single_task_and_refuses_another_project(): void {
        $this->connect();
        $link = $this->link(confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Startseite', 'description' => null]);
        $this->serve([$this->item(501, 'Startseite'), $this->item(502, 'Fremd', false, ['project_id' => 99])]);

        $this->actingAs($this->admin)->post(route('admin.vikunja.links.attach', $link), ['remote_id' => '502', 'task' => $task->sqid])->assertSessionHas('error');
        $this->post(route('admin.vikunja.links.attach', $link), ['remote_id' => '501', 'task' => $task->sqid])->assertSessionHas('success');

        $this->assertSame('Text zu Startseite', $task->fresh()?->description);
        $plugin = app(PluginManager::class)->find(VikunjaPlugin::ID);
        $this->assertStringContainsString('href="' . self::BASE . '/tasks/501"', (string) $plugin?->renderActions('task-dialog.links', $task->fresh()));
    }

    public function test_command_and_mapping_dialog_use_the_projects_of_the_token(): void {
        $this->connect();
        $this->serve([$this->item(501, 'A')]);

        $this->actingAs($this->admin)->get(route('admin.vikunja.links.create'))->assertOk()
            ->assertSee('Website')->assertSee('Intern')->assertDontSee('Favoriten');
        $this->post(route('admin.vikunja.links.store'), ['source_project_id' => '12', 'project' => $this->project->sqid])->assertRedirect();
        $this->assertSame('WEB', VikunjaProjectLink::query()->sole()->source_project_key);
        VikunjaProjectLink::query()->update(['status' => TaskImportLinkStatus::Active->value, 'import_confirmed_at' => now()]);

        $this->artisan('vikunja:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
