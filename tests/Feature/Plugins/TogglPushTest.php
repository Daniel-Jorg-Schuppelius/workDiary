<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Classification\Tag;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry};
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Support\MatchingTimeImportService;
use App\Plugins\Toggl\Services\{TogglExportService, TogglImportService, TogglOutboxDispatcher};
use App\Plugins\Toggl\{TogglConfig, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Queue};
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Spiegelung workDiary → Toggl ({@see TogglExportService}): lokal erfasste
 * Zeiten gemappter Projekte werden in Toggl angelegt, bleiben aber lokal
 * abrechenbar (`exported` bleibt false). Duplikat-Schutz gegen das
 * Import-Echo über die zusätzliche `entry`-Reference mit Response-Fingerprint.
 */
class TogglPushTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CREATE_URL = 'https://api.track.toggl.com/api/v9/workspaces/4711/time_entries';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        // Der created()-Observer (MVP-463) enqueued Create-Outbox-Einträge samt
        // Delivery-Job — im Test rufen wir den Dispatcher gezielt selbst auf.
        Queue::fake();

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->user->id])->save();
    }

    private function service(): TogglExportService {
        return new TogglExportService;
    }

    /** @return array<string, mixed> */
    private function config(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'api_token' => 'test-token',
                'workspace_id' => '4711',
                'export_enabled' => true,
            ], $extra),
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    private function mappedProject(string $togglProjectId = '9'): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Fernwartung',
            'is_default' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => $togglProjectId,
            'synced_at' => now(),
        ]);

        return $project;
    }

    private function timeEntry(Project $project, array $attributes = []): TimeEntry {
        return TimeEntry::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'date' => '2026-05-26',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 11:00:00'),
            'kind' => TimeEntryKind::Work,
            'description' => 'Fernwartung AnyDesk',
            'exported' => false,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function createdResponse(int $id = 9001): array {
        return [
            'id' => $id,
            'workspace_id' => 4711,
            'project_id' => 9,
            'start' => '2026-05-26T10:00:00+00:00',
            'stop' => '2026-05-26T11:00:00+00:00',
            'description' => 'Fernwartung AnyDesk',
            'billable' => false,
        ];
    }

    public function test_push_creates_remote_entry_and_keeps_entry_billable_locally(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);
        $tag = Tag::create(['name' => 'AnyDesk', 'organization_id' => $this->organization->id]);
        $entry->tags()->sync([$tag->id]);

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame([], $result['errors']);

        $fake->assertSent(function ($request): bool {
            if ((string) $request->getUri() !== self::CREATE_URL || $request->getMethod() !== 'POST') {
                return false;
            }
            $body = (array) json_decode((string) $request->getBody(), true);

            return ($body['created_with'] ?? null) === 'workDiary'
                && ($body['project_id'] ?? null) === 9
                && ($body['workspace_id'] ?? null) === 4711
                && ($body['tags'] ?? null) === ['AnyDesk']
                && str_starts_with((string) ($body['start'] ?? ''), '2026-05-26T10:00:00')
                && str_starts_with((string) ($body['stop'] ?? ''), '2026-05-26T11:00:00');
        });

        // Beide Referenzen: Push-Idempotenz + Import-Echo-Schutz.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'pushed_entry',
            'referenceable_id' => $entry->id,
            'external_id' => '9001',
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'referenceable_id' => $entry->id,
            'external_id' => 'toggl:9001',
        ]);
        $entryRef = ExternalReference::query()
            ->forPlugin($this->organization->id, TogglPlugin::ID, 'entry')
            ->forExternalId('toggl:9001')
            ->firstOrFail();
        $this->assertNotSame('', (string) ($entryRef->payload['fingerprint'] ?? ''));

        // Spiegelung, kein Rückbuchen: lokal bleibt der Eintrag abrechenbar.
        $this->assertFalse((bool) $entry->fresh()->exported);
    }

    public function test_push_is_idempotent_via_query_exclusion(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $first = $this->service()->exportPending($this->organization, $config);
        $second = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $first['pushed']);
        $this->assertSame(0, $second['pushed']);
        // Gepushte Einträge fallen aus der Kandidaten-Query (nicht nur skip) —
        // sonst wüchse `skipped` mit jedem Lauf, weil exported false bleibt.
        $this->assertSame(0, $second['skipped']);

        $posts = array_filter($fake->recorded(), fn (array $r): bool => $r['request']->getMethod() === 'POST');
        $this->assertCount(1, $posts);
    }

    public function test_unmapped_project_entries_are_not_candidates(): void {
        $config = $this->config();
        $unmapped = Project::factory()->create(['organization_id' => $this->organization->id, 'is_default' => false]);
        $this->timeEntry($unmapped);
        $this->mappedProject(); // Mapping existiert, aber ohne Einträge

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    private const CLIENTS_URL = 'https://api.track.toggl.com/api/v9/workspaces/4711/clients';

    private const PROJECTS_URL = 'https://api.track.toggl.com/api/v9/workspaces/4711/projects';

    /** Projekt ohne Toggl-Gegenstück beim Kunden „Musterkunde GmbH“. */
    private function unmappedCustomerProject(string $name = 'Wartung'): Project {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Musterkunde GmbH']);

        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => $name,
            'is_default' => false,
        ]);
    }

    /** Nachtrag 2026-11-04: der stille Verwurf wird zum Inbox-Hinweis je Projekt. */
    public function test_unmapped_project_leaves_a_notice_in_the_inbox(): void {
        $config = $this->config();
        $project = $this->unmappedCustomerProject();
        $this->timeEntry($project);
        $this->mappedProject();

        $fake = FakePluginHttp::fake([
            self::CLIENTS_URL . '*' => FakePluginHttp::response([], 200),
            self::PROJECTS_URL . '*' => FakePluginHttp::response([], 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
        $item = IntegrationInboxItem::query()->where('dedupe_key', TogglPlugin::ID . '-project-unmapped:' . $project->id)->firstOrFail();
        $this->assertSame(IntegrationInboxStatus::Open, $item->status);
        $this->assertSame(IntegrationInboxItem::REASON_PROJECT_UNMAPPED, $item->remote_snapshot['reason']);
        $this->assertSame('Musterkunde GmbH · Wartung', $item->display_subtitle);
        $this->assertStringContainsString('Projekt ohne Gegenstück', (string) $item->displayTitleText('Toggl'));

        // Zweiter Lauf: derselbe Hinweis, keine zweite Zeile (eindeutiger Schlüssel).
        $this->service()->exportPending($this->organization, $config);
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', TogglPlugin::ID)->count());
    }

    public function test_unmapped_project_is_matched_by_name_without_creating(): void {
        $config = $this->config();
        $project = $this->unmappedCustomerProject();
        $entry = $this->timeEntry($project);

        $fake = FakePluginHttp::fake([
            self::CLIENTS_URL . '*' => FakePluginHttp::response([['id' => 5, 'name' => 'Musterkunde GmbH', 'archived' => false]], 200),
            self::PROJECTS_URL . '*' => FakePluginHttp::response([
                ['id' => 77, 'name' => 'Wartung', 'client_id' => 6, 'workspace_id' => 4711, 'active' => true],
                ['id' => 78, 'name' => 'Wartung', 'client_id' => 5, 'workspace_id' => 4711, 'active' => true],
            ], 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse() + ['project_id' => 78], 200),
        ]);

        // Namenssuche ist auch ohne Anlage erlaubt: das Projekt existiert drüben bereits.
        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST' && str_ends_with((string) $request->getUri(), '/projects'));
        $fake->assertSent(function ($request): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return (string) $request->getUri() === self::CREATE_URL && ($body['project_id'] ?? null) === 78;
        });
        $this->assertDatabaseHas('external_references', ['plugin_id' => TogglPlugin::ID, 'external_type' => 'project_id', 'referenceable_id' => $project->id, 'external_id' => '78']);
        $this->assertDatabaseHas('external_references', ['plugin_id' => TogglPlugin::ID, 'external_type' => 'client_id', 'referenceable_id' => $project->customer_id, 'external_id' => '5']);
        $this->assertDatabaseHas('external_references', ['plugin_id' => TogglPlugin::ID, 'external_type' => 'project', 'referenceable_id' => $project->id, 'external_id' => 'musterkunde gmbh|wartung']);
        $this->assertDatabaseHas('external_references', ['plugin_id' => TogglPlugin::ID, 'external_type' => 'pushed_entry', 'referenceable_id' => $entry->id]);
    }

    public function test_create_projects_setting_creates_client_and_project_once(): void {
        $config = $this->config(['export_create_projects' => true]);
        $project = $this->unmappedCustomerProject();
        $this->timeEntry($project);
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-27 10:00:00'), 'ended_at' => CarbonImmutable::parse('2026-05-27 11:00:00'), 'date' => '2026-05-27']);

        $fake = FakePluginHttp::fake([
            self::CLIENTS_URL . '*' => function ($request) {
                return $request->getMethod() === 'POST'
                    ? FakePluginHttp::response(['id' => 5, 'name' => 'Musterkunde GmbH', 'wid' => 4711], 200)
                    : FakePluginHttp::response([], 200);
            },
            self::PROJECTS_URL . '*' => function ($request) {
                return $request->getMethod() === 'POST'
                    ? FakePluginHttp::response(['id' => 78, 'name' => 'Wartung', 'client_id' => 5, 'workspace_id' => 4711, 'active' => true], 200)
                    : FakePluginHttp::response([], 200);
            },
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse() + ['project_id' => 78], 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(2, $result['pushed']);
        $posts = array_values(array_filter($fake->recorded(), fn (array $r): bool => $r['request']->getMethod() === 'POST'));
        $this->assertSame([self::CLIENTS_URL, self::PROJECTS_URL, self::CREATE_URL, self::CREATE_URL], array_map(fn (array $r): string => (string) $r['request']->getUri(), $posts), 'Kunde und Projekt genau einmal, dann beide Einträge');
        $clientBody = (array) json_decode((string) $posts[0]['request']->getBody(), true);
        $this->assertSame(['name' => 'Musterkunde GmbH'], $clientBody);
        $projectBody = (array) json_decode((string) $posts[1]['request']->getBody(), true);
        $this->assertSame(['name' => 'Wartung', 'active' => true, 'is_private' => false, 'client_id' => 5], $projectBody);
        $this->assertDatabaseHas('external_references', ['plugin_id' => TogglPlugin::ID, 'external_type' => 'project_id', 'referenceable_id' => $project->id, 'external_id' => '78']);
        $this->assertDatabaseMissing('integration_inbox_items', ['dedupe_key' => TogglPlugin::ID . '-project-unmapped:' . $project->id]);
    }

    public function test_failed_creation_keeps_the_entry_and_reopens_the_notice(): void {
        $config = $this->config(['export_create_projects' => true]);
        $project = $this->unmappedCustomerProject();
        $this->timeEntry($project);

        FakePluginHttp::fake([
            self::CLIENTS_URL . '*' => function ($request) {
                return $request->getMethod() === 'POST' ? FakePluginHttp::response(['error' => 'forbidden'], 403) : FakePluginHttp::response([], 200);
            },
            self::PROJECTS_URL . '*' => FakePluginHttp::response([], 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertSame(0, $result['failed']);
        $item = IntegrationInboxItem::query()->where('dedupe_key', TogglPlugin::ID . '-project-unmapped:' . $project->id)->firstOrFail();
        $this->assertStringContainsString('HTTP 403', (string) ($item->remote_snapshot['local']['description'] ?? ''));

        // Verworfen bleibt verworfen; erledigt wird beim nächsten Fehlen wieder geöffnet.
        $item->forceFill(['status' => IntegrationInboxStatus::Dismissed])->save();
        $this->service()->exportPending($this->organization, $config);
        $this->assertSame(IntegrationInboxStatus::Dismissed, $item->fresh()->status);
        $item->forceFill(['status' => IntegrationInboxStatus::ResolvedLinked])->save();
        $this->service()->exportPending($this->organization, $config);
        $this->assertSame(IntegrationInboxStatus::Open, $item->fresh()->status);
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', TogglPlugin::ID)->count());
    }

    public function test_notice_is_resolved_once_the_project_transfers(): void {
        $config = $this->config();
        $project = $this->unmappedCustomerProject();
        $this->timeEntry($project);
        FakePluginHttp::fake([self::CLIENTS_URL . '*' => FakePluginHttp::response([], 200), self::PROJECTS_URL . '*' => FakePluginHttp::response([], 200)]);
        $this->service()->exportPending($this->organization, $config);
        $this->assertDatabaseHas('integration_inbox_items', ['dedupe_key' => TogglPlugin::ID . '-project-unmapped:' . $project->id, 'status' => IntegrationInboxStatus::Open->value]);

        // Der Admin legt das Projekt drüben an; die Namenssuche findet es beim nächsten Lauf.
        FakePluginHttp::fake([
            self::CLIENTS_URL . '*' => FakePluginHttp::response([['id' => 5, 'name' => 'Musterkunde GmbH', 'archived' => false]], 200),
            self::PROJECTS_URL . '*' => FakePluginHttp::response([['id' => 78, 'name' => 'wartung', 'client_id' => 5, 'workspace_id' => 4711, 'active' => true]], 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse() + ['project_id' => 78], 200),
        ]);
        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $this->assertDatabaseHas('integration_inbox_items', ['dedupe_key' => TogglPlugin::ID . '-project-unmapped:' . $project->id, 'status' => IntegrationInboxStatus::ResolvedLinked->value]);
    }

    public function test_id_backfill_runs_once_a_day_before_the_push(): void {
        $config = $this->config();
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Wartung', 'is_default' => false]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Musterkunde GmbH']);
        $project->forceFill(['customer_id' => $customer->id])->save();
        // Nur über den Namen verknüpft (Stand vieler Prod-Projekte) — ohne ID überträgt der Push nichts.
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'project',
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => 'musterkunde gmbh|wartung',
            'synced_at' => now(),
        ]);
        $this->timeEntry($project);
        $listCalls = 0;

        FakePluginHttp::fake([
            self::CLIENTS_URL . '*' => FakePluginHttp::response([['id' => 5, 'name' => 'Musterkunde GmbH', 'archived' => false]], 200),
            self::PROJECTS_URL . '*' => function () use (&$listCalls) {
                $listCalls++;

                return FakePluginHttp::response([['id' => 78, 'name' => 'Wartung', 'client_id' => 5, 'workspace_id' => 4711, 'active' => true]], 200);
            },
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse() + ['project_id' => 78], 200),
        ]);

        $first = $this->service()->exportPending($this->organization, $config);
        $this->assertSame(1, $first['pushed']);
        $this->assertDatabaseHas('external_references', ['plugin_id' => TogglPlugin::ID, 'external_type' => 'project_id', 'referenceable_id' => $project->id, 'external_id' => '78']);
        $this->assertTrue(Cache::has(TogglImportService::idBackfillKey($this->organization, \App\Plugins\Toggl\Enums\TogglSource::Track)));

        $callsAfterFirst = $listCalls;
        $this->service()->exportPending($this->organization, $config);
        $this->assertSame($callsAfterFirst, $listCalls, 'die Nachfüllung läuft am selben Tag nicht noch einmal');
    }

    public function test_export_disabled_reports_error_and_sends_nothing(): void {
        $config = $this->config(['export_enabled' => false]);
        $this->timeEntry($this->mappedProject());

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertNotSame([], $result['errors']);
        $fake->assertNothingSent();
    }

    public function test_imported_entries_are_never_pushed_back(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'toggl:111',
            'synced_at' => now(),
        ]);

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    /** Der Kern-Integrationstest: Push → nächster Import erzeugt KEIN Duplikat. */
    public function test_pushed_entry_survives_next_import_without_duplicate(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);

        FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);
        $this->service()->exportPending($this->organization, $config);

        // Nächster stündlicher Sync liefert den soeben gepushten Eintrag zurück.
        FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries*' => FakePluginHttp::response([$this->createdResponse()], 200),
            'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response([
                'email' => 'tech@example.com',
                'clients' => [['id' => 5, 'name' => 'Acme']],
                'projects' => [['id' => 9, 'name' => 'Fernwartung', 'client_id' => 5, 'workspace_id' => 4711]],
            ], 200),
        ]);

        $result = (new TogglImportService)->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_running_and_zero_minute_entries_are_not_candidates(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-26 12:00:00'), 'ended_at' => null, 'minutes' => 30]);
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-26 13:00:00'), 'ended_at' => CarbonImmutable::parse('2026-05-26 13:00:00')]);

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    public function test_api_error_counts_as_failed_and_rate_limit_aborts(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-27 10:00:00'), 'ended_at' => CarbonImmutable::parse('2026-05-27 11:00:00'), 'date' => '2026-05-27']);

        FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response(['error' => 'bad request'], 400),
        ]);
        $result = $this->service()->exportPending($this->organization, $config);
        $this->assertSame(2, $result['failed']);
        $this->assertSame(0, $result['pushed']);

        // 429 bricht den Lauf ab — der zweite Eintrag bleibt unversucht.
        FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response([], 429),
        ]);
        $result = $this->service()->exportPending($this->organization, $config);
        $this->assertSame(0, $result['pushed']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotSame([], $result['errors']);
    }

    /** Nutzerbefund 2026-09-28: 0-Minuten-Einträge (Fernwartung 20:16–20:16) liefen als Job in den Timeout. */
    public function test_zero_minute_entry_enqueues_no_create(): void {
        $this->config();
        $entry = $this->timeEntry($this->mappedProject(), [
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
        ]);

        $this->assertSame(0, (int) $entry->minutes);
        $this->assertDatabaseMissing('integration_outbox', [
            'operation' => TogglOutboxDispatcher::OP_ENTRY_CREATE,
            'idempotency_key' => TogglPlugin::ID . '-entry-create:' . $entry->getKey(),
        ]);
    }

    public function test_outbox_create_pushes_single_entry(): void {
        // MVP-463: der created()-Observer enqueued den Create; der Dispatcher
        // pusht mit denselben Schutzlinien wie der Stunden-Batch.
        $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);

        $outbox = IntegrationOutboxEntry::query()
            ->where('operation', TogglOutboxDispatcher::OP_ENTRY_CREATE)
            ->where('idempotency_key', TogglPlugin::ID . '-entry-create:' . $entry->getKey())
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox));

        $fake->assertSentCount(1);
        // Spiegel-Semantik: lokal weiter abrechenbar, beide References geschrieben.
        $this->assertFalse((bool) $entry->fresh()->exported);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->getKey(),
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'toggl:9001',
        ]);
    }

    /**
     * Vollscan 2026-08-23, B2: Der TogglApiClient läuft über das api-toolkit und
     * wirft nach ausgeschöpften Versuchen Guzzles ConnectException — der
     * Dispatcher fing nur Laravels ConnectionException, der dokumentierte
     * „kein Retry nach Transport-Timeout“-Schutz griff also nie.
     */
    public function test_outbox_create_transport_failure_is_swallowed_without_retry(): void {
        $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);

        $outbox = IntegrationOutboxEntry::query()
            ->where('operation', TogglOutboxDispatcher::OP_ENTRY_CREATE)
            ->where('idempotency_key', TogglPlugin::ID . '-entry-create:' . $entry->getKey())
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => fn(\Psr\Http\Message\RequestInterface $request) => throw new \GuzzleHttp\Exception\ConnectException('cURL error 28: Operation timed out', $request),
        ]);

        // true = erledigt: kein Queue-Retry, der stündliche toggl:push holt nach.
        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox));

        $this->assertGreaterThanOrEqual(1, count($fake->recorded()));
        $this->assertDatabaseMissing('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->getKey(),
        ]);
    }

    public function test_outbox_create_drops_already_referenced_entry(): void {
        // Rennen mit toggl:push: existiert schon eine Toggl-Referenz, ist der
        // Outbox-Create ein No-op (kein Doppel-POST).
        $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'toggl:555',
            'synced_at' => now(),
        ]);

        $outbox = IntegrationOutboxEntry::query()
            ->where('operation', TogglOutboxDispatcher::OP_ENTRY_CREATE)
            ->firstOrFail();

        $fake = FakePluginHttp::fake([]);

        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox));
        $fake->assertNothingSent();
    }

    public function test_single_workspace_is_resolved_when_not_configured(): void {
        $config = $this->config(['workspace_id' => null]);
        $project = $this->mappedProject();
        $this->timeEntry($project);

        $fake = FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/workspaces' => FakePluginHttp::response([['id' => 4711, 'name' => 'Solo WS']], 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $fake->assertSent(fn ($request): bool => (string) $request->getUri() === self::CREATE_URL && $request->getMethod() === 'POST');
    }

    /**
     * Befund 2026-11-02: Stunden-Batch und Outbox-Anlage liefen zur vollen Stunde
     * gleichzeitig; beide sahen noch keine Referenz und legten denselben Eintrag
     * zweimal in Toggl an. Die Sperre je Eintrag lässt nur einen posten.
     */
    public function test_batch_leaves_a_locked_entry_to_the_other_process(): void {
        $config = $this->config();
        $entry = $this->timeEntry($this->mappedProject());
        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $lock = TogglExportService::lockFor(TogglPlugin::ID, $this->organization, $entry);
        $this->assertTrue($lock->get());
        try {
            $result = $this->service()->exportPending($this->organization, $config);
        } finally {
            $lock->release();
        }

        $this->assertSame(['pushed' => 0, 'skipped' => 1, 'failed' => 0, 'errors' => []], $result);
        // Der Batch füllt vorab IDs nach (GET); gepostet wird unter fremder Sperre nichts.
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');

        // Freigegeben: der nächste Lauf überträgt.
        $this->assertSame(1, $this->service()->exportPending($this->organization, $config)['pushed']);
        $this->assertCount(1, array_filter($fake->recorded(), fn (array $r): bool => $r['request']->getMethod() === 'POST'));
    }

    public function test_outbox_create_is_retried_while_the_batch_holds_the_lock(): void {
        $this->config();
        $entry = $this->timeEntry($this->mappedProject());
        $outbox = IntegrationOutboxEntry::query()->where('operation', TogglOutboxDispatcher::OP_ENTRY_CREATE)->firstOrFail();
        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $lock = TogglExportService::lockFor(TogglPlugin::ID, $this->organization, $entry);
        $this->assertTrue($lock->get());
        try {
            app(TogglOutboxDispatcher::class)->dispatch($outbox);
            $this->fail('Solange der Batch die Sperre hält, muss die Outbox-Anlage in die Queue-Wiederholung gehen.');
        } catch (LockTimeoutException) {
            // Queue-Wiederholung: nach dem Backoff findet der Re-Check die Referenz des Halters.
        } finally {
            $lock->release();
        }

        $fake->assertNothingSent();
    }

    public function test_an_outbox_create_arriving_during_the_batch_post_does_not_post_again(): void {
        $config = $this->config();
        $entry = $this->timeEntry($this->mappedProject());
        $outbox = IntegrationOutboxEntry::query()->where('operation', TogglOutboxDispatcher::OP_ENTRY_CREATE)->firstOrFail();
        $concurrent = null;

        $fake = FakePluginHttp::fake([
            // Während der POST des Batches läuft, trifft die Outbox-Anlage desselben Eintrags ein.
            self::CREATE_URL => function () use ($outbox, &$concurrent) {
                try {
                    app(TogglOutboxDispatcher::class)->dispatch($outbox);
                    $concurrent = 'pushed';
                } catch (LockTimeoutException) {
                    $concurrent = 'deferred';
                }

                return FakePluginHttp::response($this->createdResponse(), 200);
            },
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame('deferred', $concurrent);
        // Der Batch füllt vorab IDs nach (GET); gepostet wird genau einmal.
        $this->assertCount(1, array_filter($fake->recorded(), fn (array $r): bool => $r['request']->getMethod() === 'POST'));
        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', TogglExportService::EXT_TYPE_PUSHED)
            ->where('referenceable_id', $entry->getKey())
            ->count());
    }
}
