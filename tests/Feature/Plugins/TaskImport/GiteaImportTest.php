<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaImportTest.php
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
use App\Plugins\Gitea\GiteaPlugin;
use App\Plugins\Gitea\Models\{GiteaConnection, GiteaProjectLink};
use App\Plugins\Gitea\Services\GiteaImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Gitea/Forgejo (MVP-1318–1320): Zugang aus den Plugin-Einstellungen mit
 * SSRF-Schranke, „Verbindung prüfen“ hält die Instanz fest, Issues je
 * Repository über `page`/`limit` mit `X-Total-Count`, Repository über die
 * stabile ID, keine Pull Requests, offen/geschlossen, Links nur auf die Instanz.
 */
class GiteaImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://git.example.test';

    private const REPO = '42';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(GiteaPlugin::ID, ['base_url' => self::BASE . '/', 'api_token' => 'gitea-token']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): GiteaConnection {
        return GiteaConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'git.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): GiteaProjectLink {
        return GiteaProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::REPO,
            'source_project_key' => 'team/app',
            'source_project_name' => 'team/app',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function issue(int $id, int $number, string $title, string $state = 'open', array $overrides = []): array {
        return $overrides + [
            'id' => $id,
            'number' => $number,
            'title' => $title,
            'body' => 'Text zu ' . $title,
            'state' => $state,
            'due_date' => null,
            'html_url' => self::BASE . '/team/app-neu/issues/' . $number,
            'pull_request' => null,
            'repository' => ['id' => (int) self::REPO, 'name' => 'app-neu', 'owner' => 'team', 'full_name' => 'team/app-neu'],
        ];
    }

    /** @param list<array<string, mixed>> $issues */
    private function serve(array $issues): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/api/v1/repositories/' . self::REPO => FakePluginHttp::response(['id' => (int) self::REPO, 'full_name' => 'team/app-neu']),
            self::BASE . '/api/v1/repos/team/app-neu/issues*' => FakePluginHttp::response($issues, 200, ['X-Total-Count' => (string) count($issues)]),
            self::BASE . '/api/v1/user/repos*' => FakePluginHttp::response([['id' => 42, 'full_name' => 'team/app-neu'], ['id' => 43, 'full_name' => 'team/docs']], 200, ['X-Total-Count' => '2']),
            self::BASE . '/api/v1/user' => FakePluginHttp::response(['id' => 7, 'login' => 'erika']),
            self::BASE . '/api/v1/version' => FakePluginHttp::response(['version' => '9.0.0+gitea-1.22.0']),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(GiteaImportService::class)->run($this->organization, $full);
    }

    private function task(int $issueId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, GiteaPlugin::ID, GiteaPlugin::EXT_TYPE_ISSUE)
            ->forExternalId(SelfHostedInstance::key(self::BASE) . ':' . $issueId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_verify_records_the_instance_and_the_page_runs_in_token_mode(): void {
        $this->serve([]);
        $this->actingAs($this->admin)->get(route('admin.gitea.index'))->assertOk()
            ->assertSee(route('admin.gitea.verify'), false)
            ->assertSee(route('admin.plugins.edit', GiteaPlugin::ID), false)
            ->assertDontSee('oauth');

        $this->post(route('admin.gitea.verify'))->assertSessionHas('success');

        $connection = GiteaConnection::query()->sole();
        $this->assertSame(SelfHostedInstance::key(self::BASE), $connection->source_id);
        $this->assertSame(SelfHostedInstance::key('http://GIT.example.test/'), $connection->source_id, 'Schema, Schreibweise und Schrägstrich ändern die Instanz nicht.');
        $this->assertSame('erika', $connection->account_name);
        $this->assertStringContainsString('9.0.0+gitea-1.22.0', (string) $connection->source_name);
        $this->assertTrue($connection->isReady());
    }

    public function test_private_address_needs_the_opt_in_and_another_instance_is_refused_while_mapped(): void {
        $this->pluginSecret(GiteaPlugin::ID, ['base_url' => 'http://192.168.10.5:3000', 'api_token' => 'gitea-token']);
        FakePluginHttp::fake(['*' => FakePluginHttp::response(['id' => 7, 'login' => 'erika', 'version' => '1.22'])]);
        $this->actingAs($this->admin)->post(route('admin.gitea.verify'))->assertSessionHas('error');
        $this->assertSame(0, GiteaConnection::query()->count());

        $this->pluginSecret(GiteaPlugin::ID, ['base_url' => 'http://192.168.10.5:3000', 'api_token' => 'gitea-token', 'allow_private_network' => true]);
        $this->post(route('admin.gitea.verify'))->assertSessionHas('success');
        $this->link();

        $this->pluginSecret(GiteaPlugin::ID, ['base_url' => self::BASE, 'api_token' => 'gitea-token', 'allow_private_network' => false]);
        $this->serve([]);
        $this->post(route('admin.gitea.verify'))->assertSessionHas('error', __('integration.task_import.flash.source_locked', ['provider' => 'Gitea/Forgejo', 'source' => 'Instanz']));
        $this->assertSame(SelfHostedInstance::key('http://192.168.10.5:3000'), GiteaConnection::query()->sole()->source_id);
    }

    public function test_import_reads_all_pages_by_total_count_and_skips_pull_requests(): void {
        $this->connect();
        $this->link();
        $pages = [
            // Gitea speichert die Frist als Datum, ausgegeben als 23:59:59 in der Serverzone.
            1 => [$this->issue(9001, 1, 'Login reparieren', 'open', ['due_date' => '2026-11-03T23:59:59Z']), $this->issue(9002, 2, 'Pull Request', 'open', ['pull_request' => ['merged' => false]])],
            2 => [$this->issue(9003, 3, 'Alt erledigt', 'closed'), $this->issue(9004, 4, 'Zweites', 'open')],
        ];
        $http = FakePluginHttp::fake([
            self::BASE . '/api/v1/repositories/' . self::REPO => FakePluginHttp::response(['id' => 42, 'full_name' => 'team/app-neu']),
            self::BASE . '/api/v1/repos/team/app-neu/issues*' => function (RequestInterface $request) use ($pages) {
                parse_str($request->getUri()->getQuery(), $query);

                // Die Instanz kappt `limit` auf 2 — das Ende ergibt sich aus X-Total-Count.
                return FakePluginHttp::response($pages[(int) ($query['page'] ?? 1)] ?? [], 200, ['X-Total-Count' => '4']);
            },
        ]);

        $counters = $this->sync();

        $this->assertSame(2, $counters['created']);
        $task = $this->task(9001);
        $this->assertSame('Login reparieren', $task?->title);
        $this->assertSame('2026-11-03', $task?->due_date?->toDateString(), 'Das Datum gilt ohne Umrechnung in die Zeitzone der Organisation.');
        $this->assertNull($this->task(9002), 'Pull Requests sind keine Aufgaben.');
        $this->assertNull($this->task(9003), 'Erledigte Altissues werden nicht angelegt.');
        $this->assertNotNull($this->task(9004));
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['page'] ?? null) === '2' && ($query['state'] ?? null) === 'open' && ($query['type'] ?? null) === 'issues';
        });
        $this->assertTrue(GiteaProjectLink::query()->sole()->is_last_run_complete);
    }

    public function test_later_runs_read_changes_closed_issues_close_and_local_progress_stays(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->issue(9001, 1, 'A'), $this->issue(9005, 5, 'B')]);
        $this->sync();
        $this->task(9005)?->forceFill(['status' => TaskStatus::InProgress])->save();

        $this->travel(20)->minutes();
        $http = $this->serve([$this->issue(9001, 1, 'A', 'closed'), $this->issue(9005, 5, 'B')]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(9001)?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task(9005)?->status);
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['state'] ?? null) === 'all' && ($query['since'] ?? null) === now()->subMinutes(30)->utc()->toRfc3339String();
        });
    }

    public function test_attach_finds_the_issue_by_id_and_links_only_point_to_the_instance(): void {
        $this->connect();
        $link = $this->link(confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Login reparieren', 'description' => null]);
        $this->serve([$this->issue(9001, 1, 'Login reparieren'), $this->issue(9006, 6, 'Fremder Link', 'open', ['html_url' => 'https://evil.example.com/team/app/issues/6'])]);

        $this->actingAs($this->admin)->post(route('admin.gitea.links.attach', $link), ['remote_id' => '9001', 'task' => $task->sqid])->assertSessionHas('success');

        $this->assertSame('Text zu Login reparieren', $task->fresh()?->description);
        $plugin = app(PluginManager::class)->find(GiteaPlugin::ID);
        $this->assertStringContainsString('href="' . self::BASE . '/team/app-neu/issues/1"', (string) $plugin?->renderActions('task-dialog.links', $task->fresh()));

        $link->forceFill(['status' => TaskImportLinkStatus::Active, 'import_confirmed_at' => now()])->save();
        $this->sync();
        $reference = ExternalReference::query()->forExternalId(SelfHostedInstance::key(self::BASE) . ':9006')->sole();
        $this->assertNull(app(GiteaImportService::class)->sourceUrl($reference));
    }

    public function test_an_empty_issue_or_a_missing_total_is_not_a_complete_read(): void {
        $this->connect();
        $link = $this->link();
        // Ladefehler bei Gitea: leeres Issue ohne Repository.
        $this->serve([$this->issue(9001, 1, 'A'), ['id' => 0, 'number' => 0, 'title' => '', 'state' => '', 'pull_request' => null, 'repository' => null]]);
        $this->sync(full: true);
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete);

        FakePluginHttp::fake([
            self::BASE . '/api/v1/repositories/' . self::REPO => FakePluginHttp::response(['id' => (int) self::REPO, 'full_name' => 'team/app-neu']),
            self::BASE . '/api/v1/repos/team/app-neu/issues*' => FakePluginHttp::response([$this->issue(9001, 1, 'A')]),
        ]);
        $this->sync(full: true);
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete, 'Ohne X-Total-Count lässt sich ein gekapptes limit nicht erkennen.');
    }

    public function test_a_forbidden_repository_fails_only_its_own_mapping(): void {
        $this->connect();
        $this->link();
        GiteaProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => '43',
            'source_project_key' => 'team/geheim',
            'source_project_name' => 'team/geheim',
            'project_id' => Project::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => TaskImportLinkStatus::Active,
            'import_confirmed_at' => now(),
        ]);
        $this->serve([$this->issue(9001, 1, 'A')]);
        FakePluginHttp::fake([
            self::BASE . '/api/v1/repositories/43' => FakePluginHttp::response(['message' => 'forbidden'], 403),
            self::BASE . '/api/v1/repositories/' . self::REPO => FakePluginHttp::response(['id' => (int) self::REPO, 'full_name' => 'team/app-neu']),
            self::BASE . '/api/v1/repos/team/app-neu/issues*' => FakePluginHttp::response([$this->issue(9001, 1, 'A')], 200, ['X-Total-Count' => '1']),
        ]);

        $this->assertSame(1, $this->sync()['created'], 'Ein 403 auf ein Repository ist kein ungültiger Zugang.');
        $this->assertTrue((bool) GiteaProjectLink::query()->where('source_project_id', self::REPO)->sole()->is_last_run_complete);
        $this->assertFalse((bool) GiteaProjectLink::query()->where('source_project_id', '43')->sole()->is_last_run_complete);
    }

    public function test_command_and_mapping_dialog_use_the_repositories_of_the_token(): void {
        $this->connect();
        $this->serve([$this->issue(9001, 1, 'A')]);

        $this->actingAs($this->admin)->get(route('admin.gitea.links.create'))->assertOk()->assertSee('team/app-neu')->assertSee('team/docs');
        $this->post(route('admin.gitea.links.store'), ['source_project_id' => '42', 'project' => $this->project->sqid])->assertRedirect();
        $this->assertSame('team/app-neu', GiteaProjectLink::query()->sole()->source_project_key);
        GiteaProjectLink::query()->update(['status' => TaskImportLinkStatus::Active->value, 'import_confirmed_at' => now()]);

        $this->artisan('gitea:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
