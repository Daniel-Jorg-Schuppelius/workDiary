<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaImportTest.php
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
use App\Plugins\Taiga\Models\{TaigaConnection, TaigaProjectLink};
use App\Plugins\Taiga\Services\TaigaImportService;
use App\Plugins\Taiga\TaigaPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Taiga (MVP-1343): Anmeldung je Lauf mit Benutzername und Passwort,
 * Projekte des Mitglieds, Userstories über `page` bis `x-pagination-next`
 * fehlt, Folgeläufe mit `modified_date__gte`, Beschreibung nur über den
 * Einzelabruf geänderter Userstories, Cloud-Links auf tree.taiga.io.
 */
class TaigaImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const API = 'https://api.taiga.io';

    private const APP = 'https://tree.taiga.io';

    private const PROJECT = '310';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->pluginSecret(TaigaPlugin::ID, ['base_url' => self::API, 'username' => 'erika', 'password' => 'geheim']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function connect(): TaigaConnection {
        return TaigaConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::API),
            'source_name' => 'api.taiga.io',
            'source_url' => self::APP,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $confirmed = true): TaigaProjectLink {
        return TaigaProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => self::PROJECT,
            'source_project_key' => 'erika-website',
            'source_project_name' => 'Website',
            'project_id' => $this->project->id,
            'status' => $confirmed ? TaskImportLinkStatus::Active : TaskImportLinkStatus::Pending,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /**
     * Listenform ohne Beschreibung.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function story(int $id, string $subject, bool $closed = false, array $overrides = []): array {
        return $overrides + [
            'id' => $id,
            'ref' => $id - 1000,
            'subject' => $subject,
            'is_closed' => $closed,
            'due_date' => null,
            'project' => (int) self::PROJECT,
            'project_extra_info' => ['id' => (int) self::PROJECT, 'name' => 'Website', 'slug' => 'erika-website'],
            'modified_date' => now()->utc()->toIso8601String(),
            'finish_date' => $closed ? now()->utc()->toIso8601String() : null,
        ];
    }

    /**
     * Listen filtern wie Taiga: `is_closed`, `modified_date__gte`, `finish_date__gte`.
     *
     * @param  list<array<string, mixed>>  $stories
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $permissions
     */
    private function serve(array $stories, array $extra = [], array $permissions = ['view_project', 'view_us']): FakePluginHttp {
        $details = [];
        foreach ($stories as $story) {
            $details[self::API . '/api/v1/userstories/' . $story['id']] = FakePluginHttp::response($story + ['description' => 'Text zu ' . $story['subject']]);
        }

        return FakePluginHttp::fake($extra + $details + [
            self::API . '/api/v1/auth' => FakePluginHttp::response(['id' => 77, 'username' => 'erika', 'full_name' => 'Erika Muster', 'auth_token' => 'jwt-abc', 'refresh' => 'r']),
            self::API . '/api/v1/userstories?*' => function (RequestInterface $request) use ($stories) {
                parse_str($request->getUri()->getQuery(), $query);
                $rows = array_values(array_filter($stories, static fn (array $story): bool => (! isset($query['is_closed']) || $story['is_closed'] === ($query['is_closed'] === 'true'))
                    && (! isset($query['modified_date__gte']) || strcmp($story['modified_date'], (string) $query['modified_date__gte']) >= 0)
                    && (! isset($query['finish_date__gte']) || ($story['finish_date'] !== null && strcmp($story['finish_date'], (string) $query['finish_date__gte']) >= 0))));

                return FakePluginHttp::response($rows, 200, ['x-paginated' => 'true', 'x-pagination-next' => '']);
            },
            self::API . '/api/v1/projects/' . self::PROJECT => FakePluginHttp::response(['id' => 310, 'name' => 'Website', 'slug' => 'erika-website', 'my_permissions' => $permissions]),
            self::API . '/api/v1/projects*' => FakePluginHttp::response([['id' => 310, 'name' => 'Website', 'slug' => 'erika-website'], ['id' => 311, 'name' => 'Intern', 'slug' => 'erika-intern']]),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(TaigaImportService::class)->run($this->organization, $full);
    }

    private function task(int $remoteId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, TaigaPlugin::ID, TaigaPlugin::EXT_TYPE_USER_STORY)
            ->forExternalId(SelfHostedInstance::key(self::API) . ':' . $remoteId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    private function storyDetailsRead(FakePluginHttp $http): int {
        return count(array_filter($http->recorded(), fn (array $entry): bool => preg_match('#/api/v1/userstories/\d+$#', $entry['request']->getUri()->getPath()) === 1));
    }

    public function test_verify_signs_in_and_lists_the_member_projects(): void {
        $http = $this->serve([]);

        $this->actingAs($this->admin)->post(route('admin.taiga.verify'))->assertSessionHas('success');

        $connection = TaigaConnection::query()->sole();
        $this->assertSame('Erika Muster', $connection->account_name);
        $this->assertSame(self::APP, $connection->source_url, 'Links zeigen in der Cloud auf die Oberfläche, nicht auf die API.');
        $http->assertSent(fn (RequestInterface $request): bool => $request->getUri()->getPath() === '/api/v1/auth'
            && json_decode((string) $request->getBody(), true) === ['type' => 'normal', 'username' => 'erika', 'password' => 'geheim']);

        $this->get(route('admin.taiga.links.create'))->assertOk()->assertSee('Website')->assertSee('Intern');
        $http->assertSent(fn (RequestInterface $request): bool => $request->getUri()->getPath() === '/api/v1/projects'
            && str_contains($request->getUri()->getQuery(), 'member=77') && $request->getHeaderLine('Authorization') === 'Bearer jwt-abc');
    }

    public function test_wrong_password_marks_the_connection_without_a_secret_in_the_message(): void {
        FakePluginHttp::fake([self::API . '/api/v1/auth' => FakePluginHttp::response(['_error_message' => 'Benutzername oder Passwort stimmen nicht.', '_error_type' => 'taiga.base.exceptions.WrongArguments'], 401)]);

        $this->actingAs($this->admin)->post(route('admin.taiga.verify'))->assertSessionHas('error');
        $this->assertSame(0, TaigaConnection::query()->count());
        $this->assertStringNotContainsString('geheim', (string) session('error'));
    }

    public function test_first_run_reads_pages_and_details_and_maps_the_fields(): void {
        $this->connect();
        $this->link();
        $pages = [
            1 => [$this->story(1001, 'Startseite', false, ['due_date' => '2026-11-04']), $this->story(1002, 'Fremd', false, ['project' => 999])],
            2 => [$this->story(1003, 'Impressum')],
        ];
        $http = $this->serve(array_merge(...array_values($pages)), [
            self::API . '/api/v1/userstories?*' => function (RequestInterface $request) use ($pages) {
                parse_str($request->getUri()->getQuery(), $query);
                $page = (int) ($query['page'] ?? 1);

                return FakePluginHttp::response($pages[$page] ?? [], 200, ['x-paginated' => 'true', 'x-pagination-next' => $page < 2 ? self::API . '/api/v1/userstories?page=' . ($page + 1) : '']);
            },
        ]);

        $this->assertSame(2, $this->sync()['created']);

        $task = $this->task(1001);
        $this->assertSame('Startseite', $task?->title);
        $this->assertSame('Text zu Startseite', $task?->description);
        $this->assertSame('2026-11-04', $task?->due_date?->toDateString());
        $this->assertNotNull($this->task(1003));
        $this->assertNull($this->task(1002));
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return $request->getUri()->getPath() === '/api/v1/userstories' && ($query['is_closed'] ?? null) === 'false' && ($query['page'] ?? null) === '2';
        });
        $plugin = app(PluginManager::class)->find(TaigaPlugin::ID);
        $this->assertStringContainsString('href="' . self::APP . '/project/erika-website/us/1"', (string) $plugin?->renderActions('task-dialog.links', $task));
    }

    public function test_later_runs_filter_by_modified_date_and_full_runs_read_details_only_for_changes(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->story(1001, 'A'), $this->story(1005, 'B')]);
        $this->sync();
        $this->task(1005)?->forceFill(['status' => TaskStatus::InProgress])->save();

        $this->travel(20)->minutes();
        // Über die Tasks geschlossen: Taiga setzt `finish_date`, nicht `modified_date`.
        $http = $this->serve([$this->story(1001, 'A', true, ['modified_date' => now()->subDay()->utc()->toIso8601String()]), $this->story(1005, 'B')]);
        $this->sync();
        $this->assertSame(TaskStatus::Done, $this->task(1001)?->status, 'Der Abschluss über Tasks kommt über finish_date__gte.');
        $this->assertSame(TaskStatus::InProgress, $this->task(1005)?->status);
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return $request->getUri()->getPath() === '/api/v1/userstories' && ($query['modified_date__gte'] ?? null) === now()->subMinutes(30)->utc()->toIso8601String() && ! isset($query['is_closed']);
        });

        $this->travel(2)->hours();
        $old = now()->subDay()->utc()->toIso8601String();
        $http = $this->serve([$this->story(1001, 'A', true, ['modified_date' => $old]), $this->story(1005, 'B', false, ['modified_date' => $old]), $this->story(1006, 'Neu')]);
        $this->sync(full: true);
        $this->assertSame(1, $this->storyDetailsRead($http), 'Der Volllauf liest Beschreibungen nur für seit dem letzten Lauf Geändertes.');
        $this->assertSame('Text zu Neu', $this->task(1006)?->description);
        $this->assertSame('Text zu B', $this->task(1005)?->description, 'Fehlende Beschreibung in der Liste löscht nichts.');
    }

    public function test_missing_story_permission_and_a_non_json_page_fail_instead_of_reading_nothing(): void {
        $this->connect();
        $link = $this->link();
        $this->serve([$this->story(1001, 'A')], permissions: ['view_project']);
        $this->sync();
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete, 'Ohne view_us wäre die Liste leer — das darf nicht als vollständig gelten.');
        $this->assertNull($this->task(1001));

        $this->serve([], [self::API . '/api/v1/userstories?*' => FakePluginHttp::response('<html>Wartung</html>', 200, ['Content-Type' => 'text/html'])]);
        $this->sync();
        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete);
        $this->assertNull($link->fresh()?->checkpoint_at);
    }

    public function test_sync_command_runs_the_confirmed_mappings(): void {
        $this->connect();
        $this->link();
        $this->serve([$this->story(1001, 'Startseite')]);

        $this->artisan('taiga:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();
    }
}
