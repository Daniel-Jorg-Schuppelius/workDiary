<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeImportTest.php
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
use App\Plugins\Leantime\LeantimePlugin;
use App\Plugins\Leantime\Models\{LeantimeConnection, LeantimeProjectLink};
use App\Plugins\Leantime\Services\LeantimeImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Leantime (MVP-1344): JSON-RPC mit `x-api-key`, Projekte des Schlüssels,
 * Status über die Statusart der Projekt-Labels, Zeitpunkte in UTC ohne Zone,
 * Meilensteine außen vor, Folgeläufe vergleichen nur Geändertes.
 */
class LeantimeImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://lt.example.test';

    private const PROJECT = '8';

    private const LABELS = [
        '3' => ['name' => 'Neu', 'statusType' => 'NEW'],
        '1' => ['name' => 'Blockiert', 'statusType' => 'INPROGRESS'],
        '4' => ['name' => 'In Arbeit', 'statusType' => 'INPROGRESS'],
        '0' => ['name' => 'Fertig', 'statusType' => 'DONE'],
        '-1' => ['name' => 'Archiviert', 'statusType' => 'DONE'],
    ];

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(LeantimePlugin::ID, ['base_url' => self::BASE, 'api_key' => 'lt_key']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): LeantimeConnection {
        return LeantimeConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'lt.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): LeantimeProjectLink {
        return LeantimeProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::PROJECT,
            'source_project_key' => self::PROJECT,
            'source_project_name' => 'Relaunch',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ticket(int $id, string $headline, int $status = 3, array $overrides = []): array {
        return $overrides + [
            'id' => $id,
            'headline' => $headline,
            'description' => '<p>Text zu ' . $headline . '</p>',
            'type' => 'task',
            'status' => $status,
            'projectId' => (int) self::PROJECT,
            'projectName' => 'Relaunch',
            'dateToFinish' => '0000-00-00 00:00:00',
            'modified' => now()->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @param  array<string, mixed>  $extra  Methode → Ergebnis (Throwable = JSON-RPC-Fehler)
     */
    private function serve(array $tickets, array $extra = []): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/api/jsonrpc' => function (RequestInterface $request) use ($tickets, $extra) {
                $call = json_decode((string) $request->getBody(), true);
                $method = substr((string) ($call['method'] ?? ''), strlen('leantime.rpc.'));
                $result = array_key_exists($method, $extra) ? $extra[$method] : match ($method) {
                    'Projects.getProjectsUserHasAccessTo' => [['id' => 8, 'name' => 'Relaunch'], ['id' => 9, 'name' => 'Intern']],
                    'Tickets.getStatusLabels' => self::LABELS,
                    'Tickets.getAll' => $tickets,
                    'Tickets.getTicket' => collect($tickets)->firstWhere('id', (int) ($call['params']['id'] ?? 0)) ?? false,
                    default => new \RuntimeException('Method not found'),
                };
                if ($result instanceof \Throwable) {
                    return FakePluginHttp::response(['jsonrpc' => '2.0', 'id' => $call['id'] ?? null, 'error' => ['code' => $result->getCode() !== 0 ? $result->getCode() : -32601, 'message' => $result->getMessage()]]);
                }

                return FakePluginHttp::response(['jsonrpc' => '2.0', 'id' => $call['id'] ?? null, 'result' => $result]);
            },
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(LeantimeImportService::class)->run($this->organization, $full);
    }

    private function task(int $remoteId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, LeantimePlugin::ID, LeantimePlugin::EXT_TYPE_TICKET)
            ->forExternalId(SelfHostedInstance::key(self::BASE) . ':' . $remoteId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_verify_reads_the_projects_of_the_key(): void {
        $http = $this->serve([]);

        $this->actingAs($this->admin)->post(route('admin.leantime.verify'))->assertSessionHas('success');
        $this->assertSame(SelfHostedInstance::key(self::BASE), LeantimeConnection::query()->sole()->source_id);
        $http->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('x-api-key') === 'lt_key'
            && $request->getUri()->getPath() === '/api/jsonrpc'
            && json_decode((string) $request->getBody(), true)['method'] === 'leantime.rpc.Projects.getProjectsUserHasAccessTo');

        $this->get(route('admin.leantime.links.create'))->assertOk()->assertSee('Relaunch')->assertSee('Intern');
    }

    public function test_first_run_maps_status_types_utc_due_dates_and_skips_milestones(): void {
        $this->connect();
        $this->link();
        $http = $this->serve([
            $this->ticket(41, 'Neu', 3, ['dateToFinish' => '2026-11-03 23:30:00']),
            $this->ticket(42, 'Blockiert', 1),
            $this->ticket(43, 'Fertig', 0),
            $this->ticket(44, 'Meilenstein', 3, ['type' => 'milestone']),
            $this->ticket(45, 'Fremd', 3, ['projectId' => 99]),
            $this->ticket(46, 'Geleerte Frist', 4, ['dateToFinish' => '1969-12-31 00:00:00']),
        ]);

        $this->assertSame(3, $this->sync()['created']);

        $this->assertSame(TaskStatus::Open, $this->task(41)?->status);
        $this->assertSame('2026-11-04', $this->task(41)?->due_date?->toDateString(), 'UTC-Zeitpunkt in der Zeitzone der Organisation.');
        $this->assertSame('Text zu Neu', $this->task(41)?->description);
        $this->assertSame(TaskStatus::InProgress, $this->task(42)?->status, 'Statusart, nicht Name: „Blockiert“ ist in Bearbeitung.');
        $this->assertNull($this->task(42)?->due_date, 'Nulldatum heißt keine Fälligkeit.');
        $this->assertNull($this->task(43));
        $this->assertNull($this->task(44));
        $this->assertNull($this->task(45));
        $this->assertNull($this->task(46)?->due_date, 'Leantime speichert ein geleertes Datum als 1969-12-31.');
        $http->assertSent(fn (RequestInterface $request): bool => (json_decode((string) $request->getBody(), true)['params']['searchCriteria'] ?? null) === ['currentProject' => 8, 'status' => 'all']);

        $plugin = app(PluginManager::class)->find(LeantimePlugin::ID);
        $this->assertStringContainsString('href="' . self::BASE . '/dashboard/home#/tickets/showTicket/41"', (string) $plugin?->renderActions('task-dialog.links', $this->task(41)));
    }

    public function test_later_runs_close_done_tickets_and_skip_unchanged(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->ticket(41, 'A'), $this->ticket(46, 'B')]);
        $this->sync();
        $this->task(46)?->forceFill(['status' => TaskStatus::InProgress])->save();

        $this->travel(20)->minutes();
        $old = now()->subDay()->utc()->format('Y-m-d H:i:s');
        // Archiviert (Status -1): kommt nur mit `status: all` und gilt als erledigt.
        $this->serve([$this->ticket(41, 'A', -1), $this->ticket(46, 'B'), $this->ticket(47, 'Alt, offen', 3, ['modified' => $old])]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(41)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(46)?->status);
        $this->assertNull($this->task(47));
    }

    public function test_rpc_error_fails_the_link_and_attach_checks_project_and_type(): void {
        $this->connect();
        $link = $this->link();
        $this->serve([], ['Tickets.getAll' => new \RuntimeException('Not authorized')]);
        $this->sync();
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete);
        $this->assertSame('LeantimeApiException', $link->fresh()?->last_error);

        $link->forceFill(['status' => TaskImportLinkStatus::Pending, 'import_confirmed_at' => null])->save();
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Neu']);
        $this->serve([$this->ticket(41, 'Neu'), $this->ticket(44, 'Meilenstein', 3, ['type' => 'milestone'])]);
        $this->actingAs($this->admin)->post(route('admin.leantime.links.attach', $link), ['remote_id' => '44', 'task' => $task->sqid])->assertSessionHas('error');
        $this->post(route('admin.leantime.links.attach', $link), ['remote_id' => '41', 'task' => $task->sqid])->assertSessionHas('success');
        $this->assertSame('Text zu Neu', $task->fresh()?->description);
    }

    public function test_versions_before_3_8_are_named_and_a_denied_ticket_is_not_visible(): void {
        $this->serve([], ['Projects.getProjectsUserHasAccessTo' => new \RuntimeException('Required Parameter Missing: userId', -32602)]);
        $this->actingAs($this->admin)->post(route('admin.leantime.verify'))->assertSessionHas('error');
        $this->assertStringContainsString('LeantimeVersionUnsupportedException', (string) session('error'));

        $this->connect();
        $link = $this->link(confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Neu']);
        $this->serve([], ['Tickets.getTicket' => new \RuntimeException('Forbidden', -32001)]);
        $this->post(route('admin.leantime.links.attach', $link), ['remote_id' => '41', 'task' => $task->sqid])
            ->assertSessionHas('error', __('integration.task_import.attach_refused.not_visible'));
    }

    public function test_sync_command_runs_the_confirmed_mappings(): void {
        $this->connect();
        $this->link();
        $this->serve([$this->ticket(41, 'Neu')]);

        $this->artisan('leantime:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
