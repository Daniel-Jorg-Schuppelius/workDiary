<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Clockify\{ClockifyConfig, ClockifyExportService, ClockifyImportService, ClockifyPlugin};
use App\Plugins\Support\MatchingTimeImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Spiegelung workDiary → Clockify ({@see ClockifyExportService}, Audit 2026-08
 * Welle 1.1 / Konsolidierungs-Audit C5): lokal erfasste Zeiten gemappter
 * Projekte werden in Clockify angelegt, bleiben aber lokal abrechenbar.
 * Projekt-Mapping läuft — anders als Toggl — über die namensbasierten
 * `project`-References gegen die Workspace-Projektliste (String-IDs).
 */
class ClockifyPushTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CREATE_URL = 'https://api.clockify.me/api/v1/workspaces/ws1/time-entries';

    private const PROJECTS_URL = 'https://api.clockify.me/api/v1/workspaces/ws1/projects*';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Queue::fake();

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->user->id])->save();
    }

    private function service(): ClockifyExportService {
        return new ClockifyExportService;
    }

    /** @return array<string, mixed> */
    private function config(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => ClockifyPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'api_key' => 'test-key',
                'workspace_id' => 'ws1',
                'export_enabled' => true,
            ], $extra),
        ]);

        return ClockifyConfig::resolve($this->organization->id);
    }

    /** Projekt mit namensbasierter `project`-Reference („client|projekt", lowercase). */
    private function mappedProject(): Project {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme']);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Fernwartung',
            'is_default' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => ClockifyPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => 'acme|fernwartung',
            'synced_at' => now(),
        ]);

        return $project;
    }

    /** @return list<array<string, mixed>> */
    private function projectList(): array {
        return [
            ['id' => 'ckp9', 'name' => 'Fernwartung', 'clientName' => 'Acme'],
            ['id' => 'ckp10', 'name' => 'Anderes', 'clientName' => 'Beta'],
        ];
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
    private function createdResponse(string $id = 'ck9001'): array {
        return [
            'id' => $id,
            'projectId' => 'ckp9',
            'timeInterval' => [
                'start' => '2026-05-26T10:00:00Z',
                'end' => '2026-05-26T11:00:00Z',
            ],
            'description' => 'Fernwartung AnyDesk',
            'billable' => false,
        ];
    }

    public function test_push_creates_remote_entry_and_keeps_entry_billable_locally(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);

        $fake = FakePluginHttp::fake([
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
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

            return ($body['projectId'] ?? null) === 'ckp9'
                && str_starts_with((string) ($body['start'] ?? ''), '2026-05-26T10:00:00')
                && str_starts_with((string) ($body['end'] ?? ''), '2026-05-26T11:00:00');
        });

        // Beide Referenzen: Push-Idempotenz + Import-Echo-Schutz.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => ClockifyPlugin::ID,
            'external_type' => ClockifyExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->id,
            'external_id' => 'ck9001',
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => ClockifyPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_id' => $entry->id,
            'external_id' => 'api:ck9001',
        ]);
        $entryRef = ExternalReference::query()
            ->forPlugin($this->organization->id, ClockifyPlugin::ID, MatchingTimeImportService::EXT_TYPE_ENTRY)
            ->forExternalId('api:ck9001')
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
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $first = $this->service()->exportPending($this->organization, $config);
        $second = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $first['pushed']);
        $this->assertSame(0, $second['pushed']);
        $this->assertSame(0, $second['skipped']);

        $posts = array_filter($fake->recorded(), fn (array $r): bool => $r['request']->getMethod() === 'POST' && str_contains((string) $r['request']->getUri(), 'time-entries'));
        $this->assertCount(1, $posts);
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
            'plugin_id' => ClockifyPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'api:ck111',
            'synced_at' => now(),
        ]);

        $fake = FakePluginHttp::fake([
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST' && str_contains((string) $request->getUri(), 'time-entries'));
    }

    public function test_unmapped_project_entries_are_not_candidates(): void {
        $config = $this->config();
        $unmapped = Project::factory()->create(['organization_id' => $this->organization->id, 'is_default' => false]);
        $this->timeEntry($unmapped);
        $this->mappedProject(); // Mapping existiert, aber ohne Einträge

        $fake = FakePluginHttp::fake([
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST' && str_contains((string) $request->getUri(), 'time-entries'));
    }

    public function test_rate_limit_aborts_run(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-27 10:00:00'), 'ended_at' => CarbonImmutable::parse('2026-05-27 11:00:00'), 'date' => '2026-05-27']);

        FakePluginHttp::fake([
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
            self::CREATE_URL => FakePluginHttp::response([], 429),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        // Free-Plan-Drosselung: Lauf endet, der zweite Eintrag bleibt unversucht.
        $this->assertSame(0, $result['pushed']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotSame([], $result['errors']);
    }

    public function test_outbox_create_pushes_single_entry(): void {
        // Welle 1.2: der created()-Observer enqueued den Create (Gate:
        // export_enabled, Spiegel-Semantik wie Toggl); der Dispatcher pusht
        // mit denselben Schutzlinien wie der Stunden-Batch.
        $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);

        $outbox = \App\Models\IntegrationOutboxEntry::query()
            ->where('operation', \App\Plugins\Clockify\Services\ClockifyOutboxDispatcher::OP_ENTRY_CREATE)
            ->where('idempotency_key', ClockifyPlugin::ID . '-entry-create:' . $entry->getKey())
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $this->assertTrue(app(\App\Plugins\Clockify\Services\ClockifyOutboxDispatcher::class)->dispatch($outbox));

        // Spiegel-Semantik: lokal weiter abrechenbar, beide References geschrieben.
        $this->assertFalse((bool) $entry->fresh()->exported);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => ClockifyPlugin::ID,
            'external_type' => ClockifyExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'ck9001',
        ]);
    }

    /** Der Kern-Integrationstest: Push → nächster Import erzeugt KEIN Duplikat. */
    public function test_pushed_entry_survives_next_import_without_duplicate(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);

        FakePluginHttp::fake([
            self::PROJECTS_URL => FakePluginHttp::response($this->projectList(), 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);
        $this->service()->exportPending($this->organization, $config);

        // Nächster Sync (Reports-API) liefert den soeben gepushten Eintrag zurück.
        FakePluginHttp::fake([
            'https://reports.api.clockify.me/v1/workspaces/ws1/reports/detailed' => FakePluginHttp::response([
                'timeentries' => [[
                    '_id' => 'ck9001',
                    'clientName' => 'Acme',
                    'projectName' => 'Fernwartung',
                    'description' => 'Fernwartung AnyDesk',
                    'billable' => false,
                    'timeInterval' => ['start' => '2026-05-26T10:00:00Z', 'end' => '2026-05-26T11:00:00Z'],
                ]],
            ], 200),
        ]);

        $result = (new ClockifyImportService)->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated'] ?? 0);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }
}
