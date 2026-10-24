<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiImportTest.php
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
use App\Plugins\Glpi\GlpiPlugin;
use App\Plugins\Glpi\Models\{GlpiConnection, GlpiProjectLink};
use App\Plugins\Glpi\Services\GlpiImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * GLPI (MVP-1337): Sitzung aus Benutzer- und App-Token, Entitäten als
 * Quellen, aktive Entität vor dem Lesen, `range` bis `Content-Range`,
 * Statusnummern, Lösungsfrist als Datum, maskierte Inhalte älterer Versionen.
 */
class GlpiImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://glpi.example.test';

    private const ENTITY = '3';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(GlpiPlugin::ID, ['base_url' => self::BASE, 'app_token' => 'app-tok', 'user_token' => 'user-tok']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): GlpiConnection {
        return GlpiConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'glpi.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): GlpiProjectLink {
        return GlpiProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::ENTITY,
            'source_project_key' => self::ENTITY,
            'source_project_name' => 'Kunde A',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ticket(int $id, string $name, int $status = 1, array $overrides = []): array {
        return $overrides + [
            'id' => $id,
            'name' => $name,
            'content' => '<p>Störung: ' . $name . '</p>',
            'status' => $status,
            'entities_id' => (int) self::ENTITY,
            'time_to_resolve' => null,
            'date_mod' => now()->utc()->format('Y-m-d H:i:s'),
            'is_deleted' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @param  array<string, mixed>  $extra
     */
    private function serve(array $tickets, array $extra = []): FakePluginHttp {
        $single = [];
        foreach ($tickets as $ticket) {
            $single[self::BASE . '/apirest.php/Ticket/' . $ticket['id'] . '?*'] = FakePluginHttp::response($ticket);
        }
        $count = count($tickets);

        return FakePluginHttp::fake($extra + $single + [
            self::BASE . '/apirest.php/initSession*' => FakePluginHttp::response(['session_token' => 'sess-1']),
            self::BASE . '/apirest.php/changeActiveEntities*' => FakePluginHttp::response('true'),
            self::BASE . '/apirest.php/getFullSession*' => FakePluginHttp::response(['session' => ['glpiID' => 5, 'glpiname' => 'erika', 'glpifriendlyname' => 'Erika Muster']]),
            self::BASE . '/apirest.php/getMyEntities*' => FakePluginHttp::response(['myentities' => [['id' => 0, 'name' => 'Stammentität'], ['id' => 3, 'name' => 'Kunde A']]]),
            self::BASE . '/apirest.php/Ticket/*' => FakePluginHttp::response(['ERROR_ITEM_NOT_FOUND', 'Not found'], 404),
            self::BASE . '/apirest.php/Ticket?*' => FakePluginHttp::response($tickets, 200, ['Content-Range' => '0-' . max(0, $count - 1) . '/' . $count]),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(GlpiImportService::class)->run($this->organization, $full);
    }

    private function task(int $remoteId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, GlpiPlugin::ID, GlpiPlugin::EXT_TYPE_TICKET)
            ->forExternalId(SelfHostedInstance::key(self::BASE) . ':' . $remoteId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_session_tokens_and_entities_as_sources(): void {
        $http = $this->serve([]);

        $this->actingAs($this->admin)->post(route('admin.glpi.verify'))->assertSessionHas('success');
        $this->assertSame('Erika Muster', GlpiConnection::query()->sole()->account_name);
        $http->assertSent(fn (RequestInterface $request): bool => str_ends_with($request->getUri()->getPath(), '/initSession')
            && $request->getHeaderLine('Authorization') === 'user_token user-tok' && $request->getHeaderLine('App-Token') === 'app-tok');
        $http->assertSent(fn (RequestInterface $request): bool => str_ends_with($request->getUri()->getPath(), '/getFullSession')
            && $request->getHeaderLine('Session-Token') === 'sess-1' && $request->getHeaderLine('Authorization') === '' && $request->getHeaderLine('App-Token') === 'app-tok');

        $this->get(route('admin.glpi.links.create'))->assertOk()->assertSee('Stammentität')->assertSee('Kunde A');
        $this->post(route('admin.glpi.links.store'), ['source_project_id' => '0', 'project' => $this->project->sqid])->assertRedirect();
        $this->assertSame('0', GlpiProjectLink::query()->sole()->source_project_id, 'Die Stammentität hat die ID 0.');
    }

    public function test_first_run_activates_the_entity_reads_ranges_and_maps_status(): void {
        $this->connect();
        $this->link();
        $all = [
            $this->ticket(901, 'Neu', 1, ['time_to_resolve' => '2026-11-04 18:00:00']),
            $this->ticket(902, 'Zugewiesen', 2),
            $this->ticket(903, 'Gelöst', 5),
            $this->ticket(904, 'Fremde Entität', 1, ['entities_id' => 7]),
            $this->ticket(905, 'Wartend', 4, ['content' => '&#60;p&#62;Alte &#38;amp; maskierte Version&#60;/p&#62;']),
            $this->ticket(908, 'Zur Genehmigung &#38; Prüfung', 10),
        ];
        $http = $this->serve([], [
            self::BASE . '/apirest.php/Ticket?*' => function (RequestInterface $request) use ($all) {
                parse_str($request->getUri()->getQuery(), $query);
                [$from] = array_map('intval', explode('-', (string) ($query['range'] ?? '0-1')));
                // Die Instanz begrenzt den Bereich auf zwei Einträge.
                $slice = array_slice($all, $from, 2);

                return FakePluginHttp::response($slice, 206, ['Content-Range' => $from . '-' . ($from + count($slice) - 1) . '/' . count($all)]);
            },
        ]);

        $this->assertSame(4, $this->sync()['created']);

        $this->assertSame(TaskStatus::Open, $this->task(901)?->status);
        $this->assertSame(TaskStatus::Open, $this->task(908)?->status, 'Status 10 (Genehmigung, GLPI 11) ist offen.');
        $this->assertSame('Zur Genehmigung & Prüfung', $this->task(908)?->title, 'Maskierte Namen älterer Versionen werden entschlüsselt.');
        $this->assertSame('2026-11-04', $this->task(901)?->due_date?->toDateString());
        $this->assertSame('Störung: Neu', $this->task(901)?->description);
        $this->assertSame(TaskStatus::InProgress, $this->task(902)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(905)?->status);
        $this->assertSame('Alte & maskierte Version', $this->task(905)?->description);
        $this->assertNull($this->task(903));
        $this->assertNull($this->task(904));
        $http->assertSent(fn (RequestInterface $request): bool => str_ends_with($request->getUri()->getPath(), '/changeActiveEntities')
            && json_decode((string) $request->getBody(), true) === ['entities_id' => 3, 'is_recursive' => false]);
        $http->assertSent(fn (RequestInterface $request): bool => str_contains(urldecode($request->getUri()->getQuery()), 'range=4-')
            && $request->getHeaderLine('X-GLPI-Sanitized-Content') === 'false');
        $plugin = app(PluginManager::class)->find(GlpiPlugin::ID);
        $this->assertStringContainsString('href="' . self::BASE . '/front/ticket.form.php?id=901"', (string) $plugin?->renderActions('task-dialog.links', $this->task(901)));
    }

    public function test_later_runs_close_solved_tickets_with_a_zone_margin(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->ticket(901, 'A'), $this->ticket(902, 'B', 2)]);
        $this->sync();

        $this->travel(20)->minutes();
        $http = $this->serve([
            $this->ticket(901, 'A', 6),
            // Änderung vor drei Stunden in Instanzzeit — liegt im Spielraum und wird verglichen.
            $this->ticket(902, 'B geändert', 2, ['date_mod' => now()->subHours(3)->format('Y-m-d H:i:s')]),
            $this->ticket(906, 'Alt, offen', 1, ['date_mod' => now()->subDays(2)->format('Y-m-d H:i:s')]),
        ]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(901)?->status);
        $this->assertSame('B geändert', $this->task(902)?->title);
        $this->assertNull($this->task(906));
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/Ticket') && ($query['sort'] ?? null) === 'date_mod' && ($query['order'] ?? null) === 'DESC';
        });
        $http->assertSent(fn (RequestInterface $request): bool => str_ends_with($request->getUri()->getPath(), '/killSession'));
    }

    public function test_a_refused_entity_change_or_a_shrinking_list_is_not_a_complete_read(): void {
        $this->connect();
        $link = $this->link();
        $this->serve([$this->ticket(901, 'A')], [self::BASE . '/apirest.php/changeActiveEntities*' => FakePluginHttp::response('false')]);
        $this->sync();
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete, 'GLPI meldet den abgelehnten Wechsel mit 200 und false.');
        $this->assertNull($this->task(901));

        $pages = [0 => [$this->ticket(901, 'A'), $this->ticket(902, 'B')], 2 => [$this->ticket(904, 'D')]];
        $this->serve([], [self::BASE . '/apirest.php/Ticket?*' => function (RequestInterface $request) use ($pages) {
            parse_str($request->getUri()->getQuery(), $query);
            $from = (int) explode('-', (string) ($query['range'] ?? '0-1'))[0];

            // Zwischen den Seiten wird ein Ticket gelöscht: die Gesamtzahl fällt von 4 auf 3.
            return FakePluginHttp::response($pages[$from] ?? [], 206, ['Content-Range' => $from . '-' . ($from + 1) . '/' . ($from === 0 ? 4 : 3)]);
        }]);
        $this->sync(full: true);
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete);
    }

    public function test_attach_refuses_other_entities_and_trashed_tickets(): void {
        $this->connect();
        $link = $this->link(confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Neu']);
        $this->serve([$this->ticket(901, 'Neu'), $this->ticket(907, 'Papierkorb', 1, ['is_deleted' => 1])]);

        $this->actingAs($this->admin)->post(route('admin.glpi.links.attach', $link), ['remote_id' => '907', 'task' => $task->sqid])->assertSessionHas('error');
        $this->post(route('admin.glpi.links.attach', $link), ['remote_id' => '901', 'task' => $task->sqid])->assertSessionHas('success');
        $this->assertSame('Störung: Neu', $task->fresh()?->description);
    }

    public function test_sync_command_runs_the_confirmed_mappings(): void {
        $this->connect();
        $this->link();
        $this->serve([$this->ticket(901, 'Neu')]);

        $this->artisan('glpi:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
