<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneImportTest.php
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
use App\Plugins\Plane\Models\{PlaneConnection, PlaneProjectLink};
use App\Plugins\Plane\PlanePlugin;
use App\Plugins\Plane\Services\PlaneImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Plane (MVP-1342): X-API-Key, Workspace als Instanz (Cloud-Links auf
 * app.plane.so), Cursor-Seiten bis `next_page_results`, Status aus der
 * Zustandsgruppe, Zieldatum ohne Zeitzone, Rückfall auf `issues/` für ältere
 * Instanzen.
 */
class PlaneImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const API = 'https://api.plane.so';

    private const WORKSPACE = 'https://app.plane.so/acme';

    private const PROJECT = '6a3e2c1b-0d4f-4a5b-9c8d-7e6f5a4b3c2d';

    private const S_BACKLOG = '11111111-1111-4111-8111-111111111111';

    private const S_STARTED = '22222222-2222-4222-8222-222222222222';

    private const S_DONE = '33333333-3333-4333-8333-333333333333';

    private const S_CANCELLED = '44444444-4444-4444-8444-444444444444';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Pacific/Auckland']);
        $this->pluginSecret(PlanePlugin::ID, ['base_url' => self::API, 'workspace_slug' => 'acme', 'api_key' => 'plane_api_x']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): PlaneConnection {
        return PlaneConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::WORKSPACE),
            'source_name' => 'app.plane.so/acme',
            'source_url' => self::WORKSPACE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): PlaneProjectLink {
        return PlaneProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::PROJECT,
            'source_project_key' => 'WEB',
            'source_project_name' => 'Website',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    private static function uuid(int $n): string {
        return sprintf('aaaaaaaa-0000-4000-8000-%012d', $n);
    }

    /**
     * @param  string|array<string, string>  $state  UUID oder — mit `expand=state` — das Zustandsobjekt
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(int $n, string $title, string|array $state = self::S_BACKLOG, array $overrides = []): array {
        return $overrides + [
            'id' => self::uuid($n),
            'name' => $title,
            'description_html' => '<p>Text zu ' . $title . '</p>',
            'state' => $state,
            'target_date' => null,
            'sequence_id' => $n,
            'project' => self::PROJECT,
            'updated_at' => now()->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     */
    private function serve(array $items, string $itemsPath = 'work-items', array $extra = []): FakePluginHttp {
        $base = self::API . '/api/v1/workspaces/acme/projects/' . self::PROJECT;
        $single = [];
        foreach ($items as $item) {
            $single[$base . '/' . $itemsPath . '/' . $item['id'] . '/'] = FakePluginHttp::response($item);
        }
        $other = $itemsPath === 'work-items' ? 'issues' : 'work-items';

        return FakePluginHttp::fake($extra + $single + [
            $base . '/states/*' => FakePluginHttp::response(['next_page_results' => false, 'next_cursor' => '100:1:0', 'results' => [
                ['id' => self::S_BACKLOG, 'name' => 'Eingang', 'group' => 'backlog'],
                ['id' => self::S_STARTED, 'name' => 'Läuft', 'group' => 'started'],
                ['id' => self::S_DONE, 'name' => 'Fertig', 'group' => 'completed'],
                ['id' => self::S_CANCELLED, 'name' => 'Verworfen', 'group' => 'cancelled'],
            ]]),
            $base . '/' . $itemsPath . '/*' => FakePluginHttp::response(['next_page_results' => false, 'next_cursor' => '100:1:0', 'results' => $items]),
            $base . '/' . $other . '/*' => FakePluginHttp::response(['detail' => 'Not found.'], 404),
            self::API . '/api/v1/workspaces/acme/projects/*' => FakePluginHttp::response(['next_page_results' => false, 'results' => [
                ['id' => self::PROJECT, 'name' => 'Website', 'identifier' => 'WEB', 'archived_at' => null, 'is_member' => true],
                ['id' => 'bbbbbbbb-0000-4000-8000-000000000001', 'name' => 'Archiv', 'identifier' => 'ARC', 'archived_at' => '2026-01-01T00:00:00Z', 'is_member' => true],
                ['id' => 'bbbbbbbb-0000-4000-8000-000000000002', 'name' => 'Öffentlich', 'identifier' => 'PUB', 'archived_at' => null, 'is_member' => false],
            ]]),
            self::API . '/api/v1/users/me/' => FakePluginHttp::response(['id' => 'cccccccc-0000-4000-8000-000000000001', 'display_name' => 'erika', 'email' => 'erika@example.test']),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(PlaneImportService::class)->run($this->organization, $full);
    }

    private function task(int $n): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, PlanePlugin::ID, PlanePlugin::EXT_TYPE_WORK_ITEM)
            ->forExternalId(SelfHostedInstance::key(self::WORKSPACE) . ':' . self::uuid($n))->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_verify_takes_the_workspace_in_the_cloud_app_as_instance(): void {
        $http = $this->serve([]);

        $this->actingAs($this->admin)->post(route('admin.plane.verify'))->assertSessionHas('success');

        $connection = PlaneConnection::query()->sole();
        $this->assertSame(SelfHostedInstance::key(self::WORKSPACE), $connection->source_id);
        $this->assertSame(self::WORKSPACE, $connection->source_url);
        $this->assertSame('erika', $connection->account_name);
        $http->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('X-API-Key') === 'plane_api_x');

        $this->get(route('admin.plane.links.create'))->assertOk()->assertSee('Website')->assertDontSee('Archiv')
            ->assertDontSee('Öffentlich', false);
    }

    public function test_cursor_pages_state_groups_and_target_date(): void {
        $this->connect();
        $this->link();
        $pages = [
            '' => ['next_page_results' => true, 'next_cursor' => '2:1:0', 'results' => [
                $this->item(1, 'Eingang', self::S_BACKLOG, ['target_date' => '2026-11-04']),
                $this->item(2, 'Läuft', self::S_STARTED),
            ]],
            '2:1:0' => ['next_page_results' => false, 'next_cursor' => '2:2:0', 'results' => [
                // Mit `expand=state` käme der Zustand als Objekt — die Gruppe gilt auch dann.
                $this->item(6, 'Aufgeklappt', ['id' => self::S_STARTED, 'name' => 'Läuft', 'group' => 'started']),
                $this->item(3, 'Fertig', self::S_DONE),
                $this->item(4, 'Verworfen', self::S_CANCELLED),
                $this->item(5, 'Fremdes Projekt', self::S_BACKLOG, ['project' => 'dddddddd-0000-4000-8000-000000000001']),
            ]],
        ];
        $http = $this->serve([], extra: [
            self::API . '/api/v1/workspaces/acme/projects/' . self::PROJECT . '/work-items/*' => function (RequestInterface $request) use ($pages) {
                parse_str($request->getUri()->getQuery(), $query);

                return FakePluginHttp::response($pages[(string) ($query['cursor'] ?? '')] ?? ['results' => []]);
            },
        ]);

        $this->assertSame(3, $this->sync()['created']);

        $this->assertSame(TaskStatus::InProgress, $this->task(6)?->status);
        $http->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/work-items/') && ($query['order_by'] ?? null) === 'sequence_id';
        });
        $this->assertSame(TaskStatus::Open, $this->task(1)?->status);
        $this->assertSame('2026-11-04', $this->task(1)?->due_date?->toDateString(), 'Zieldatum ist ein Datum — keine Umrechnung in die Zeitzone.');
        $this->assertSame('Text zu Eingang', $this->task(1)?->description);
        $this->assertSame(TaskStatus::InProgress, $this->task(2)?->status);
        $this->assertNull($this->task(3), 'Abgeschlossene übernimmt der Erstlauf nicht.');
        $this->assertNull($this->task(4));
        $this->assertNull($this->task(5));

        $this->serve([$this->item(1, 'Eingang', self::S_CANCELLED), $this->item(2, 'Läuft', self::S_DONE)]);
        $this->sync(full: true);
        $this->assertSame(TaskStatus::Done, $this->task(1)?->status, 'Verworfen gilt als erledigt.');
        $this->assertSame(TaskStatus::Done, $this->task(2)?->status);
    }

    public function test_older_instances_fall_back_to_issues_and_links_point_to_the_app(): void {
        $this->connect();
        $link = $this->link(confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Eingang']);
        $this->serve([$this->item(1, 'Eingang')], 'issues');

        $this->actingAs($this->admin)->post(route('admin.plane.links.attach', $link), ['remote_id' => self::uuid(1), 'task' => $task->sqid])->assertSessionHas('success');
        $this->assertSame('Text zu Eingang', $task->fresh()?->description);
        $plugin = app(PluginManager::class)->find(PlanePlugin::ID);
        $this->assertStringContainsString('href="' . self::WORKSPACE . '/projects/' . self::PROJECT . '/issues/' . self::uuid(1) . '"', (string) $plugin?->renderActions('task-dialog.links', $task->fresh()));

        $link->forceFill(['status' => TaskImportLinkStatus::Active, 'import_confirmed_at' => now()])->save();
        $this->serve([$this->item(1, 'Eingang'), $this->item(2, 'Neu')], 'issues');
        $this->assertSame(1, $this->sync()['created']);
    }

    public function test_later_runs_compare_only_changed_items(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->item(1, 'A'), $this->item(2, 'B')]);
        $this->sync();
        $this->task(2)?->forceFill(['status' => TaskStatus::InProgress])->save();

        $this->travel(20)->minutes();
        $old = now()->subDay()->utc()->toIso8601String();
        $this->serve([$this->item(1, 'A', self::S_DONE), $this->item(2, 'B'), $this->item(6, 'Alt, offen', self::S_BACKLOG, ['updated_at' => $old])]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(1)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(2)?->status);
        $this->assertNull($this->task(6));
    }

    public function test_sync_command_runs_the_confirmed_mappings(): void {
        $this->connect();
        $this->link();
        $this->serve([$this->item(1, 'Eingang')]);

        $this->artisan('plane:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
