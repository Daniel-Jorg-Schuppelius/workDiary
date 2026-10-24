<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\{Organization, User};
use App\Models\Project\{Project, Task};
use App\Plugins\Asana\Api\{AsanaClientFactory, AsanaOAuthGrant};
use App\Plugins\Asana\AsanaPlugin;
use App\Plugins\Asana\Models\AsanaConnection;
use App\Plugins\Asana\Services\AsanaImportService;
use App\Plugins\Support\OAuthConnectionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Asana (MVP-1307/1308): Anmeldung mit PKCE und Workspace aus dem Konto,
 * Widerruf beim Trennen, fremde Verbindung nicht nutzbar; Import über
 * `next_page.offset` mit `opt_fields` und `completed_since`, Neustart nach
 * abgelaufenem Offset, Mehrfachzugehörigkeit, Zeitzone der Fälligkeit.
 */
class AsanaImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->configureTaskImportApps();
        $this->enableTaskImportPlugin(AsanaPlugin::ID);
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(AsanaImportService::class)->run($this->organization, $full);
    }

    private function task(string $gid): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, AsanaPlugin::ID, AsanaPlugin::EXT_TYPE_TASK)
            ->forExternalId(self::ASANA_WORKSPACE . ':' . $gid)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    public function test_consent_uses_pkce_and_offers_the_workspaces_of_the_account(): void {
        $this->fakeTokenEndpoint(AsanaOAuthGrant::class, [self::tokenResponse()]);
        FakePluginHttp::fake(['https://app.asana.com/api/1.0/users/me*' => FakePluginHttp::response(['data' => [
            'gid' => '900001', 'name' => 'Erika Muster',
            'workspaces' => [['gid' => self::ASANA_WORKSPACE, 'name' => 'Muster Workspace'], ['gid' => 'kein-gid', 'name' => 'Kaputt']],
        ]])]);

        $location = (string) $this->post(route('admin.asana.oauth.start'))->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://app.asana.com/-/oauth_authorize?', $location);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertSame('tasks:read projects:read workspaces:read users:read', $query['scope'] ?? null);

        $this->get(route('admin.asana.oauth.callback', ['state' => $query['state'], 'code' => 'c-1']))->assertSessionHas('success');

        parse_str((string) $this->tokenRequests[0]->getBody(), $form);
        $this->assertNotEmpty($form['code_verifier'] ?? null);
        $connection = AsanaConnection::query()->firstOrFail();
        $this->assertSame('Erika Muster', $connection->account_name);
        $this->assertSame([self::ASANA_WORKSPACE], array_column($connection->availableSources(), 'id'));
        $this->assertNull($connection->source_id);
    }

    public function test_disconnect_revokes_the_refresh_token_and_clears_the_connection(): void {
        $connection = $this->asanaConnection();
        $this->fakeTokenEndpoint(AsanaOAuthGrant::class, [new \GuzzleHttp\Psr7\Response(200)]);

        $this->post(route('admin.asana.disconnect'))->assertSessionHas('success');

        $this->assertSame('https://app.asana.com/-/oauth_revoke', (string) $this->tokenRequests[0]->getUri());
        parse_str((string) $this->tokenRequests[0]->getBody(), $form);
        $this->assertSame('asana-refresh', $form['token'] ?? null);
        $connection->refresh();
        $this->assertSame(OAuthConnectionStatus::Disconnected, $connection->status);
        $this->assertNull($connection->refresh_token);
    }

    public function test_a_foreign_connection_is_never_used(): void {
        $other = Organization::factory()->create();
        $this->asanaConnection(['access_token' => 'fremd'], $other);

        $this->expectException(\RuntimeException::class);
        app(AsanaClientFactory::class)->for((int) $this->organization->id);
    }

    public function test_import_pages_with_offset_and_maps_completion_and_due_dates(): void {
        $this->asanaConnection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->asanaLink($project);
        $http = FakePluginHttp::fake([$this->asanaTasksUrl() => function (RequestInterface $request) {
            parse_str($request->getUri()->getQuery(), $query);

            return FakePluginHttp::response(($query['offset'] ?? null) === 'off-2'
                ? ['data' => [
                    $this->asanaTask('5003', 'Spät fällig', false, ['due_at' => '2026-11-03T23:30:00.000Z']),
                    $this->asanaTask('5004', 'Anderer Workspace', false, ['workspace' => ['gid' => '1299999999999999']]),
                ], 'next_page' => null]
                : ['data' => [
                    $this->asanaTask('5001', 'Angebot schreiben', false, ['due_on' => '2026-11-10']),
                    $this->asanaTask('5002', 'Schon erledigt', true),
                ], 'next_page' => ['offset' => 'off-2', 'path' => '/projects/x/tasks?offset=off-2', 'uri' => 'https://evil.example.com/x']]);
        }]);

        $counters = $this->sync();

        $this->assertSame(2, $counters['created']);
        $this->assertSame('2026-11-10', $this->task('5001')?->due_date?->toDateString());
        $this->assertSame('Notiz Angebot schreiben', $this->task('5001')?->description);
        $this->assertSame('2026-11-04', $this->task('5003')?->due_date?->toDateString(), 'due_at in der Zeitzone der Organisation.');
        $this->assertNull($this->task('5002'), 'Erledigte Aufgaben werden nicht neu angelegt.');
        $this->assertNull($this->task('5004'), 'Aufgaben anderer Workspaces werden verworfen.');
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_contains((string) ($query['opt_fields'] ?? ''), 'memberships.project.gid')
                && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string) ($query['completed_since'] ?? '')) === 1
                && ($query['limit'] ?? null) === '100';
        });
        $http->assertNotSent(fn (RequestInterface $request): bool => $request->getUri()->getHost() !== 'app.asana.com');
    }

    public function test_completion_since_the_last_run_closes_and_false_keeps_local_progress(): void {
        $this->freezeSecond();
        $this->asanaConnection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->asanaLink($project);
        FakePluginHttp::fake([$this->asanaTasksUrl() => FakePluginHttp::response(['data' => [$this->asanaTask('5001', 'A'), $this->asanaTask('5002', 'B')], 'next_page' => null])]);
        $this->sync();
        $this->task('5002')?->forceFill(['status' => TaskStatus::InProgress])->save();

        $this->travel(20)->minutes();
        $http = FakePluginHttp::fake([$this->asanaTasksUrl() => FakePluginHttp::response(['data' => [$this->asanaTask('5001', 'A', true), $this->asanaTask('5002', 'B')], 'next_page' => null])]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task('5001')?->status);
        $this->assertSame(TaskStatus::InProgress, $this->task('5002')?->status);
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['completed_since'] ?? '') === now()->subMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z');
        });

        // Wiedereröffnet in Asana → lokal wieder offen, weil der Abschluss aus Asana kam.
        FakePluginHttp::fake([$this->asanaTasksUrl() => FakePluginHttp::response(['data' => [$this->asanaTask('5001', 'A')], 'next_page' => null])]);
        $this->sync();
        $this->assertSame(TaskStatus::Open, $this->task('5001')?->status);
    }

    public function test_expired_offset_restarts_the_project_once(): void {
        $this->asanaConnection();
        $this->asanaLink(Project::factory()->create(['organization_id' => $this->organization->id]));
        $calls = 0;
        FakePluginHttp::fake([$this->asanaTasksUrl() => function (RequestInterface $request) use (&$calls) {
            $calls++;
            parse_str($request->getUri()->getQuery(), $query);
            if (($query['offset'] ?? null) === 'stale') {
                return FakePluginHttp::response(['errors' => [['message' => 'offset expired']]], 400);
            }

            return FakePluginHttp::response($calls === 1
                ? ['data' => [$this->asanaTask('5001', 'A')], 'next_page' => ['offset' => 'stale']]
                : ['data' => [$this->asanaTask('5001', 'A'), $this->asanaTask('5002', 'B')], 'next_page' => null]);
        }]);

        $counters = $this->sync();

        $this->assertSame(2, $counters['created']);
        $this->assertSame(3, $calls);
        $this->assertSame(2, Task::query()->count());
    }

    public function test_task_in_two_mapped_projects_is_merged_or_becomes_a_case(): void {
        $this->asanaConnection();
        $target = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->asanaLink($target, '1300000000000001');
        $this->asanaLink($target, '1300000000000002');
        $shared = fn (string $p): array => $this->asanaTask('5001', 'Gemeinsam', false, ['memberships' => [['project' => ['gid' => '1300000000000001']], ['project' => ['gid' => '1300000000000002']]]], $p);
        FakePluginHttp::fake([
            $this->asanaTasksUrl('1300000000000001') => FakePluginHttp::response(['data' => [$shared('1300000000000001')], 'next_page' => null]),
            $this->asanaTasksUrl('1300000000000002') => FakePluginHttp::response(['data' => [$shared('1300000000000002'), $this->asanaTask('5009', 'Zweit', false, ['memberships' => [['project' => ['gid' => '1300000000000002']], ['project' => ['gid' => '1300000000000003']]]], '1300000000000002')], 'next_page' => null]),
            $this->asanaTasksUrl('1300000000000003') => FakePluginHttp::response(['data' => [$this->asanaTask('5009', 'Zweit', false, ['memberships' => [['project' => ['gid' => '1300000000000002']], ['project' => ['gid' => '1300000000000003']]]], '1300000000000003')], 'next_page' => null]),
        ]);
        $this->sync();
        $this->assertSame(1, Task::query()->where('title', 'Gemeinsam')->count(), 'Gleiches Ziel: eine Aufgabe.');

        $this->asanaLink(Project::factory()->create(['organization_id' => $this->organization->id]), '1300000000000003');
        $this->sync();
        $this->assertNotNull($this->task('5009'), 'Bestand vor der zweiten Zuordnung bleibt.');
        $this->assertSame(1, Task::query()->where('title', 'Zweit')->count(), 'Bestand wird nicht verdoppelt oder verschoben.');
        $this->assertSame((int) $target->id, (int) $this->task('5009')?->project_id);

        // Neue Aufgabe in zwei Projekten mit verschiedenen Zielen → Klärfall vor der Anlage.
        FakePluginHttp::fake([
            $this->asanaTasksUrl('1300000000000001') => FakePluginHttp::response(['data' => [], 'next_page' => null]),
            $this->asanaTasksUrl('1300000000000002') => FakePluginHttp::response(['data' => [$this->asanaTask('5010', 'Zwei Ziele', false, ['memberships' => [['project' => ['gid' => '1300000000000002']], ['project' => ['gid' => '1300000000000003']]]], '1300000000000002')], 'next_page' => null]),
            $this->asanaTasksUrl('1300000000000003') => FakePluginHttp::response(['data' => [$this->asanaTask('5010', 'Zwei Ziele', false, ['memberships' => [['project' => ['gid' => '1300000000000002']], ['project' => ['gid' => '1300000000000003']]]], '1300000000000003')], 'next_page' => null]),
        ]);
        $this->sync();
        $this->assertNull($this->task('5010'));
        $this->assertTrue(IntegrationInboxItem::query()->where('dedupe_key', 'task:' . self::ASANA_WORKSPACE . ':5010:ambiguous_target')->exists());
    }

    public function test_attach_keeps_local_progress_because_asana_reports_none(): void {
        $this->asanaConnection();
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $link = $this->asanaLink($project, confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'title' => 'A', 'status' => TaskStatus::InProgress]);
        FakePluginHttp::fake(['https://app.asana.com/api/1.0/tasks/5001*' => FakePluginHttp::response(['data' => $this->asanaTask('5001', 'A')])]);

        $outcome = app(AsanaImportService::class)->attach($this->organization, $link, '5001', $task);

        $this->assertSame('updated', $outcome);
        $this->assertSame(TaskStatus::InProgress, $task->fresh()?->status);
        $this->assertSame('Notiz A', $task->fresh()?->description);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }

    public function test_command_runs_per_organization_and_skips_unconnected_ones(): void {
        $this->asanaConnection();
        $this->asanaLink(Project::factory()->create(['organization_id' => $this->organization->id]));
        $other = Organization::factory()->create();
        $this->enableTaskImportPlugin(AsanaPlugin::ID, $other);
        $this->asanaLink(Project::factory()->create(['organization_id' => $other->id]));
        $http = FakePluginHttp::fake([$this->asanaTasksUrl() => FakePluginHttp::response(['data' => [$this->asanaTask('5001', 'A')], 'next_page' => null])]);

        $this->artisan('asana:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();

        $this->assertSame(1, Task::query()->withoutGlobalScopes()->count(), 'Ohne Verbindung wird für die zweite Organisation nichts gelesen.');
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['completed_since'] ?? '') === '1970-01-01T00:00:00Z';
        });
    }
}
