<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardImportTest.php
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
use App\Plugins\Kanboard\KanboardPlugin;
use App\Plugins\Kanboard\Models\{KanboardConnection, KanboardProjectLink};
use App\Plugins\Kanboard\Services\KanboardImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Kanboard (MVP-1341): JSON-RPC mit Anwendungszugang (`jsonrpc`) oder
 * persönlichem Token, Projekte über `getAllProjects` bzw. `getMyProjects`,
 * Aufgaben über `getAllTasks` je Status, Status nur aus `is_active`,
 * Unix-Sekunden als Fälligkeit, JSON-RPC-Fehler brechen den Lauf.
 */
class KanboardImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://kanban.example.test';

    private const PROJECT = '4';

    private User $admin;

    private Project $project;

    /** So viele Anfragen beantwortet der Server zuerst mit 503. */
    private int $unavailable = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(KanboardPlugin::ID, ['base_url' => self::BASE, 'api_token' => 'kb-token']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): KanboardConnection {
        return KanboardConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'kanban.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): KanboardProjectLink {
        return KanboardProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::PROJECT,
            'source_project_key' => 'OPS',
            'source_project_name' => 'Betrieb',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(int $id, string $title, bool $active = true, array $overrides = []): array {
        return $overrides + [
            'id' => (string) $id,
            'title' => $title,
            'description' => 'Text zu ' . $title,
            'is_active' => $active ? '1' : '0',
            'date_due' => '0',
            'project_id' => self::PROJECT,
            'column_id' => '2',
            'reference' => '',
            'date_modification' => (string) now()->getTimestamp(),
        ];
    }

    /**
     * Beantwortet JSON-RPC-Aufrufe nach Prozedur; `$tasks` je `status_id`.
     *
     * @param  array<int, list<array<string, mixed>>>  $tasks
     * @param  array<string, mixed>  $extra  Prozedur → Ergebnis
     */
    private function serve(array $tasks, array $extra = []): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/jsonrpc.php' => function (RequestInterface $request) use ($tasks, $extra) {
                if ($this->unavailable > 0) {
                    $this->unavailable--;

                    return FakePluginHttp::response('Service Unavailable', 503, ['Retry-After' => '0']);
                }
                $call = json_decode((string) $request->getBody(), true);
                $method = $call['method'] ?? '';
                $result = array_key_exists($method, $extra) ? $extra[$method] : match ($method) {
                    'getVersion' => '1.2.40',
                    'getAllProjects' => [['id' => '4', 'name' => 'Betrieb', 'identifier' => 'OPS', 'is_active' => '1'], ['id' => '5', 'name' => 'Vertrieb', 'identifier' => '', 'is_active' => '1'], ['id' => '6', 'name' => 'Altprojekt', 'identifier' => '', 'is_active' => '0']],
                    'getProjectById' => (string) ($call['params']['project_id'] ?? '') === self::PROJECT ? ['id' => self::PROJECT, 'name' => 'Betrieb', 'is_active' => '1'] : null,
                    'getAllTasks' => $tasks[(int) ($call['params']['status_id'] ?? 1)] ?? [],
                    'getTask' => collect($tasks)->flatten(1)->firstWhere('id', (string) ($call['params']['task_id'] ?? '')),
                    default => null,
                };
                if ($result instanceof \Throwable) {
                    return FakePluginHttp::response(['jsonrpc' => '2.0', 'id' => $call['id'] ?? null, 'error' => ['code' => -32603, 'message' => $result->getMessage()]]);
                }

                return FakePluginHttp::response(['jsonrpc' => '2.0', 'id' => $call['id'] ?? null, 'result' => $result]);
            },
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(KanboardImportService::class)->run($this->organization, $full);
    }

    private function task(int $remoteId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, KanboardPlugin::ID, KanboardPlugin::EXT_TYPE_TASK)
            ->forExternalId(SelfHostedInstance::key(self::BASE) . ':' . $remoteId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    private function sentMethod(FakePluginHttp $http, string $method): bool {
        return array_filter($http->recorded(), fn (array $entry): bool => (json_decode((string) $entry['request']->getBody(), true)['method'] ?? null) === $method) !== [];
    }

    public function test_application_access_verifies_and_lists_all_projects(): void {
        $http = $this->serve([]);

        $this->actingAs($this->admin)->post(route('admin.kanboard.verify'))->assertSessionHas('success');
        $connection = KanboardConnection::query()->sole();
        $this->assertSame('jsonrpc', $connection->account_name);
        $this->assertStringContainsString('1.2.40', (string) $connection->source_name);
        $http->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('Authorization') === 'Basic ' . base64_encode('jsonrpc:kb-token'));

        $this->get(route('admin.kanboard.links.create'))->assertOk()->assertSee('Betrieb')->assertSee('Vertrieb')->assertDontSee('Altprojekt');
        $this->assertFalse($this->sentMethod($http, 'getMe'), 'getMe lehnt Kanboard für den Anwendungszugang ab.');
    }

    public function test_personal_token_uses_get_me_and_get_my_projects(): void {
        $this->pluginSecret(KanboardPlugin::ID, ['base_url' => self::BASE, 'username' => 'erika', 'api_token' => 'personal']);
        $http = $this->serve([], [
            'getMe' => ['id' => '7', 'username' => 'erika', 'name' => 'Erika Muster'],
            'getMyProjects' => [['id' => '4', 'name' => 'Betrieb', 'identifier' => 'OPS']],
        ]);

        $this->actingAs($this->admin)->post(route('admin.kanboard.verify'))->assertSessionHas('success');
        $this->assertSame('Erika Muster', KanboardConnection::query()->sole()->account_name);
        $this->get(route('admin.kanboard.links.create'))->assertOk()->assertSee('Betrieb')->assertDontSee('Vertrieb');
        $this->assertFalse($this->sentMethod($http, 'getAllProjects'));
        $http->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('Authorization') === 'Basic ' . base64_encode('erika:personal'));
    }

    public function test_first_run_reads_open_tasks_and_maps_status_due_date_and_link(): void {
        $this->connect();
        $this->link();
        $due = now()->setTimezone('Europe/Berlin')->setDate(2026, 11, 4)->startOfDay()->getTimestamp();
        $http = $this->serve([1 => [
            $this->item(31, 'Backup prüfen', true, ['date_due' => (string) $due, 'reference' => 'OPS-31']),
            $this->item(32, 'Fremdes Projekt', true, ['project_id' => '9']),
        ], 0 => [$this->item(33, 'Geschlossen', false)]]);

        $this->assertSame(1, $this->sync()['created']);

        $task = $this->task(31);
        $this->assertSame('Backup prüfen', $task?->title);
        $this->assertSame('2026-11-04', $task?->due_date?->toDateString());
        $this->assertNull($this->task(32));
        $this->assertNull($this->task(33));
        $http->assertNotSent(fn (RequestInterface $request): bool => (json_decode((string) $request->getBody(), true)['params']['status_id'] ?? null) === 0);
        $plugin = app(PluginManager::class)->find(KanboardPlugin::ID);
        $this->assertStringContainsString('task_id=31&amp;project_id=4', (string) $plugin?->renderActions('task-dialog.links', $task));
    }

    public function test_read_only_calls_are_retried_after_503(): void {
        $this->connect();
        $this->link();
        $this->unavailable = 1;
        $this->serve([1 => [$this->item(31, 'Backup prüfen', true)]]);

        $this->assertSame(1, $this->sync()['created'], 'JSON-RPC läuft über POST, die Lesemethoden dürfen trotzdem wiederholt werden.');
    }

    public function test_later_runs_read_closed_tasks_close_them_and_skip_old_changes(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([1 => [$this->item(31, 'A'), $this->item(34, 'B')]]);
        $this->sync();
        $this->task(34)?->forceFill(['status' => TaskStatus::InProgress])->save();

        $this->travel(20)->minutes();
        $old = (string) now()->subDay()->getTimestamp();
        $this->serve([1 => [$this->item(34, 'B'), $this->item(35, 'Alt, offen', true, ['date_modification' => $old])], 0 => [$this->item(31, 'A', false)]]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(31)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(34)?->status);
        $this->assertNull($this->task(35), 'Vor dem Aufholpunkt geänderte Aufgaben vergleicht erst der Volllauf.');

        $this->sync(full: true);
        $this->assertNotNull($this->task(35));
    }

    public function test_json_rpc_error_fails_the_run_and_attach_checks_the_project(): void {
        $this->connect();
        $link = $this->link();
        $this->serve([], ['getAllTasks' => new \RuntimeException('Access denied')]);
        $this->sync();
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete);
        $this->assertNull($link->fresh()?->checkpoint_at, 'Ohne vollständiges Lesen rückt der Aufholpunkt nicht vor.');
        $this->assertSame('KanboardApiException', $link->fresh()?->last_error);

        $link->forceFill(['status' => TaskImportLinkStatus::Pending, 'import_confirmed_at' => null])->save();
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Backup']);
        $this->serve([1 => [$this->item(31, 'Backup'), $this->item(32, 'Fremd', true, ['project_id' => '9'])]]);
        $this->actingAs($this->admin)->post(route('admin.kanboard.links.attach', $link), ['remote_id' => '32', 'task' => $task->sqid])->assertSessionHas('error');
        $this->post(route('admin.kanboard.links.attach', $link), ['remote_id' => '31', 'task' => $task->sqid])->assertSessionHas('success');
        $this->assertSame('Text zu Backup', $task->fresh()?->description);
    }

    public function test_a_missing_project_fails_instead_of_reading_an_empty_list(): void {
        $this->connect();
        $link = $this->link();
        $this->serve([1 => [$this->item(31, 'A')]], ['getProjectById' => null]);

        $this->sync(full: true);

        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete, 'getAllTasks prüft das Projekt mit dem Anwendungszugang nicht.');
        $this->assertNull($this->task(31));
    }

    public function test_sync_command_runs_the_confirmed_mappings(): void {
        $this->connect();
        $this->link();
        $this->serve([1 => [$this->item(31, 'Backup prüfen')]]);

        $this->artisan('kanboard:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
