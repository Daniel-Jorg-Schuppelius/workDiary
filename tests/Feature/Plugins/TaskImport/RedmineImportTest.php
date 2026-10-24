<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Integration\{IntegrationInboxStatus, TaskImportLinkStatus};
use App\Enums\Task\TaskStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Models\Time\TimeEntry;
use App\Plugins\PluginManager;
use App\Plugins\Redmine\Models\{RedmineConnection, RedmineProjectLink};
use App\Plugins\Redmine\RedminePlugin;
use App\Plugins\Redmine\Services\RedmineImportService;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Redmine (MVP-1321–1324): Issues über `offset`/`total_count` ohne
 * Unterprojekte, erledigt nur über `is_closed` (ältere Instanzen über die
 * Statusliste), Zeiteinträge als Dauer ohne Uhrzeit im Zielprojekt, Benutzer
 * nur über ausdrückliche Zuordnung, Löschungen nur im vollständig gelesenen
 * Fenster, abgerechnete Zeiten werden zum Konflikt.
 */
class RedmineImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://redmine.example.test';

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret(RedminePlugin::ID, ['base_url' => self::BASE, 'api_key' => 'redmine-key']);
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function redmineInstance(): string {
        return SelfHostedInstance::key(self::BASE);
    }

    private function connect(): RedmineConnection {
        return RedmineConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => $this->redmineInstance(),
            'source_name' => 'redmine.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
        ]);
    }

    private function link(bool $times = false): RedmineProjectLink {
        return RedmineProjectLink::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_project_id' => '7',
            'source_project_key' => 'portal',
            'source_project_name' => 'Kundenportal',
            'project_id' => $this->project->id,
            'status' => TaskImportLinkStatus::Active,
            'import_confirmed_at' => now(),
            'is_time_import_enabled' => $times,
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function issue(int $id, string $subject, array $status = ['id' => 1, 'name' => 'Neu', 'is_closed' => false], array $overrides = []): array {
        return $overrides + ['id' => $id, 'subject' => $subject, 'description' => 'Text ' . $id, 'status' => $status, 'due_date' => null, 'project' => ['id' => 7, 'name' => 'Kundenportal']];
    }

    /** @return array<string, mixed> */
    private function timeEntry(int $id, int $userId, float $hours, string $spentOn, ?int $issueId = null): array {
        return ['id' => $id, 'project' => ['id' => 7, 'name' => 'Kundenportal'], 'issue' => $issueId !== null ? ['id' => $issueId] : null, 'user' => ['id' => $userId, 'name' => 'Erika Muster'], 'activity' => ['id' => 9, 'name' => 'Entwicklung'], 'hours' => $hours, 'comments' => 'Arbeit ' . $id, 'spent_on' => $spentOn];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @param  list<array<string, mixed>>  $times
     */
    private function serve(array $issues, array $times = []): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/issues.json*' => FakePluginHttp::response(['issues' => $issues, 'total_count' => count($issues), 'offset' => 0, 'limit' => 100]),
            self::BASE . '/time_entries.json*' => FakePluginHttp::response(['time_entries' => $times, 'total_count' => count($times), 'offset' => 0, 'limit' => 100]),
            self::BASE . '/issue_statuses.json' => FakePluginHttp::response(['issue_statuses' => [['id' => 1, 'name' => 'Neu', 'is_closed' => false], ['id' => 5, 'name' => 'Erledigt', 'is_closed' => true]]]),
            self::BASE . '/users/current.json' => FakePluginHttp::response(['user' => ['id' => 3, 'login' => 'erika', 'firstname' => 'Erika', 'lastname' => 'Muster']]),
            self::BASE . '/projects.json*' => FakePluginHttp::response(['projects' => [['id' => 7, 'identifier' => 'portal', 'name' => 'Kundenportal']], 'total_count' => 1]),
        ]);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(RedmineImportService::class)->run($this->organization, $full);
    }

    private function task(int $issueId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, RedminePlugin::ID, RedminePlugin::EXT_TYPE_ISSUE)
            ->forExternalId($this->redmineInstance() . ':' . $issueId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    private function mapErika(User $user): void {
        $this->actingAs($this->admin)->post(route('admin.redmine.users.map'), ['redmine_user' => $this->redmineInstance() . ':3', 'user' => $user->sqid])->assertSessionHas('success');
    }

    public function test_verify_and_page_with_time_section(): void {
        $this->serve([]);
        $this->actingAs($this->admin)->post(route('admin.redmine.verify'))->assertSessionHas('success');
        $this->assertSame('Erika Muster', RedmineConnection::query()->sole()->account_name);
        $this->link();

        $this->get(route('admin.redmine.index'))->assertOk()
            ->assertSee(__('redmine::redmine.times.title'))
            ->assertSee(route('admin.redmine.links.times', RedmineProjectLink::query()->sole()), false);
    }

    public function test_issues_over_pages_use_is_closed_and_fall_back_to_the_status_list(): void {
        $this->connect();
        $this->link();
        $http = FakePluginHttp::fake([
            self::BASE . '/issues.json*' => function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);

                return FakePluginHttp::response((int) ($query['offset'] ?? 0) === 0
                    ? ['issues' => [$this->issue(101, 'Login reparieren', ['id' => 1, 'name' => 'Neu'], ['due_date' => '2026-11-20']), $this->issue(102, 'Unterprojekt', ['id' => 1, 'name' => 'Neu'], ['project' => ['id' => 8, 'name' => 'Sub']])], 'total_count' => 3, 'offset' => 0, 'limit' => 2]
                    : ['issues' => [$this->issue(103, 'Unbekannter Status', ['id' => 42, 'name' => 'Eigener'])], 'total_count' => 3, 'offset' => 2, 'limit' => 2]);
            },
            self::BASE . '/issue_statuses.json' => FakePluginHttp::response(['issue_statuses' => [['id' => 1, 'name' => 'Neu', 'is_closed' => false]]]),
        ]);

        $counters = $this->sync();

        $this->assertSame(1, $counters['created']);
        $this->assertSame('2026-11-20', $this->task(101)?->due_date?->toDateString());
        $this->assertSame(TaskStatus::Open, $this->task(101)?->status);
        $this->assertNull($this->task(102), 'Unterprojekte gehören nicht zur Zuordnung.');
        $this->assertNull($this->task(103));
        $this->assertTrue(IntegrationInboxItem::query()->where('dedupe_key', 'issue:' . $this->redmineInstance() . ':103:unknown_status')->exists());
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['subproject_id'] ?? null) === '!*' && ($query['status_id'] ?? null) === 'open' && ($query['offset'] ?? null) === '2';
        });
    }

    public function test_closed_issue_closes_and_delta_reads_all_statuses_since_the_checkpoint(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link();
        $this->serve([$this->issue(101, 'A')]);
        $this->sync();

        $this->travel(20)->minutes();
        $http = $this->serve([$this->issue(101, 'A', ['id' => 5, 'name' => 'Erledigt', 'is_closed' => true])]);
        $this->sync();

        $this->assertSame(TaskStatus::Done, $this->task(101)?->status);
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['status_id'] ?? null) === '*' && ($query['updated_on'] ?? null) === '>=' . now()->subMinutes(30)->utc()->format('Y-m-d\TH:i:s\Z');
        });
        $this->assertSame(self::BASE . '/issues/101', app(RedmineImportService::class)->sourceUrl(ExternalReference::query()->forReferenceable($this->task(101))->sole()));
    }

    public function test_time_entries_wait_for_an_explicit_user_mapping_and_are_booked_as_duration(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link(times: true);
        $today = now()->toDateString();
        $this->serve([$this->issue(101, 'A')], [$this->timeEntry(5001, 3, 1.5, $today, 101)]);

        $this->sync();
        $this->assertSame(0, TimeEntry::query()->count(), 'Ohne Benutzerzuordnung wird nicht gebucht.');
        $pending = IntegrationInboxItem::query()->where('plugin_id', RedminePlugin::ID)->where('status', IntegrationInboxStatus::Open)->sole();
        $this->assertSame('integration.task_import.case.time_user_unmapped', $pending->display_subtitle);
        $this->actingAs($this->admin)->get(route('admin.redmine.index'))->assertSee(__('redmine::redmine.users.waiting', ['name' => 'Erika Muster', 'count' => 1]));

        $worker = $this->orgUser();
        $this->mapErika($worker);
        $this->sync();

        $entry = TimeEntry::query()->sole();
        $this->assertSame((int) $worker->id, (int) $entry->user_id);
        $this->assertSame(90, (int) $entry->minutes);
        $this->assertSame($today, $entry->date?->toDateString());
        $this->assertNull($entry->started_at, 'Redmine kennt keine Uhrzeit — keine erfundene.');
        $this->assertSame((int) $this->project->id, (int) $entry->project_id);
        $this->assertSame((int) $this->task(101)?->id, (int) $entry->task_id);
        $this->assertSame(IntegrationInboxStatus::ResolvedCreated, $pending->fresh()?->status);
    }

    public function test_changed_hours_update_deleted_entries_vanish_and_billed_times_become_conflicts(): void {
        $this->freezeSecond();
        $this->connect();
        $this->link(times: true);
        $worker = $this->orgUser();
        $this->mapErika($worker);
        $today = now()->toDateString();
        $this->serve([], [$this->timeEntry(5001, 3, 1.0, $today), $this->timeEntry(5002, 3, 2.0, $today)]);
        app(RedmineImportService::class)->importTimes($this->organization);
        $this->assertSame(2, TimeEntry::query()->count());

        $this->serve([], [$this->timeEntry(5001, 3, 1.25, $today)]);
        $counters = app(PluginManager::class)->find(RedminePlugin::ID)?->importTimeEntries($this->organization);

        $this->assertSame(1, $counters['updated'] ?? null);
        $this->assertSame(1, $counters['removed'] ?? null);
        $this->assertSame([75], TimeEntry::query()->pluck('minutes')->map(fn ($m): int => (int) $m)->all());

        TimeEntry::query()->update(['exported' => true]);
        $this->serve([], [$this->timeEntry(5001, 3, 3.0, $today)]);
        $counters = app(RedmineImportService::class)->importTimes($this->organization);
        $this->assertSame(1, $counters['conflicts']);
        $this->assertSame(75, (int) TimeEntry::query()->sole()->minutes, 'Abgerechnete Zeiten werden nicht überschrieben.');
    }

    public function test_time_import_stays_off_until_enabled_and_the_command_runs_both(): void {
        $this->connect();
        $link = $this->link();
        $worker = $this->orgUser();
        $this->mapErika($worker);
        $this->serve([$this->issue(101, 'A')], [$this->timeEntry(5001, 3, 1.0, now()->toDateString())]);

        $this->artisan('redmine:sync')->assertSuccessful();
        $this->assertSame(0, TimeEntry::query()->count());

        $this->actingAs($this->admin)->post(route('admin.redmine.links.times', $link))->assertSessionHas('success');
        $this->assertTrue($link->fresh()?->is_time_import_enabled);
        $this->artisan('redmine:sync', ['--full' => true])->assertSuccessful();
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertNotNull($link->fresh()?->last_time_import_at);
    }

    public function test_time_entries_leave_out_subprojects_and_an_invalid_key_stops_both_imports(): void {
        $this->connect();
        $this->link(times: true);
        $this->mapErika($this->orgUser());
        $http = $this->serve([$this->issue(101, 'A')], [$this->timeEntry(5001, 3, 1.0, now()->toDateString())]);
        $this->sync();
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_contains($request->getUri()->getPath(), '/time_entries.json') && ($query['subproject_id'] ?? null) === '!*' && ($query['project_id'] ?? null) === '7';
        });

        // Redmine liest einen ungültigen Schlüssel als anonym weiter; nur das Konto antwortet mit 401.
        $http = FakePluginHttp::fake([
            self::BASE . '/users/current.json' => FakePluginHttp::response(['errors' => ['Unauthorized']], 401),
            self::BASE . '/issues.json*' => FakePluginHttp::response(['issues' => [], 'total_count' => 0, 'offset' => 0, 'limit' => 100]),
            self::BASE . '/time_entries.json*' => FakePluginHttp::response(['time_entries' => [], 'total_count' => 0, 'offset' => 0, 'limit' => 100]),
        ]);
        foreach ([fn (): array => $this->sync(full: true), fn (): array => app(RedmineImportService::class)->importTimes($this->organization)] as $run) {
            try {
                $run();
                $this->fail('Ein abgewiesener Schlüssel darf keinen Lauf durchlassen.');
            } catch (\App\Plugins\Support\PluginApiException $e) {
                $this->assertSame(401, $e->status);
            }
        }

        $http->assertNotSent(fn (RequestInterface $request): bool => ! str_contains($request->getUri()->getPath(), '/users/current.json'));
        $this->assertNotNull($this->task(101), 'Ohne vollständiges Lesen wird nichts als gelöscht gemeldet.');
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame(2, (int) RedmineConnection::query()->sole()->consecutive_failures);
    }

    public function test_a_shrinking_total_between_pages_is_not_a_complete_read(): void {
        $this->connect();
        $link = $this->link();
        FakePluginHttp::fake([
            self::BASE . '/issues.json*' => function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);

                return FakePluginHttp::response((int) ($query['offset'] ?? 0) === 0
                    ? ['issues' => [$this->issue(101, 'A'), $this->issue(102, 'B')], 'total_count' => 4, 'offset' => 0, 'limit' => 2]
                    : ['issues' => [$this->issue(104, 'D')], 'total_count' => 3, 'offset' => 2, 'limit' => 2]);
            },
            self::BASE . '/users/current.json' => FakePluginHttp::response(['user' => ['id' => 3, 'login' => 'erika']]),
        ]);

        $this->sync(full: true);

        $this->assertFalse((bool) $link->fresh()?->is_last_run_complete, 'Eine gelöschte Issue verschiebt die Folgeseite — 103 wäre übersprungen.');
        $this->assertSame('IncompleteRemoteReadException', $link->fresh()?->last_error);
    }
}
