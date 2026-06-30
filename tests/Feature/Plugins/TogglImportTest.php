<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Toggl\Sources\TogglEntry;
use App\Plugins\Toggl\{TogglConfig, TogglImportService, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TogglImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function service(): TogglImportService {
        return new TogglImportService;
    }

    private function enableToggl(): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => [
                'api_token' => 'test-token',
            ],
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    /** Kunde "Acme" mit Projekt "Website" — matchbar über Namensgleichheit. */
    private function customerWithProject(string $customerName, string $projectName): Project {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $customerName,
        ]);

        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => $projectName,
            'is_default' => false,
        ]);
    }

    /** group_key wie vom Import erzeugt (lower(client|project)). */
    private function groupKey(string $client, string $project): string {
        return mb_strtolower(trim($client) . '|' . trim($project));
    }

    private function seedInboxEntry(string $client, string $project, string $entryKey, string $start, string $end, bool $billable = true): IntegrationInboxItem {
        return IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'csv',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => $entryKey,
            'dedupe_key' => 'entry:' . $entryKey,
            'group_key' => $this->groupKey($client, $project),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => 'csv',
                'entry_key' => $entryKey,
                'client_name' => $client,
                'project_name' => $project,
                'description' => null,
                'started_at' => CarbonImmutable::parse($start)->toIso8601String(),
                'ended_at' => CarbonImmutable::parse($end)->toIso8601String(),
                'billable' => $billable,
                'user_email' => null,
            ],
            'display_title' => $project,
            'display_subtitle' => $client,
            'occurred_at' => CarbonImmutable::parse($start),
        ]);
    }

    private function fakeApi(array $timeEntries, array $clients, array $projects): void {
        Http::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries*' => Http::response($timeEntries, 200),
            'https://api.track.toggl.com/api/v9/me*' => Http::response([
                'email' => 'tech@example.com',
                'clients' => $clients,
                'projects' => $projects,
            ], 200),
        ]);
    }

    public function test_api_import_books_entry_in_matched_project(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => true,
                'description' => 'Bugfix',
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $result = $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['unmatched']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(45, $entry->minutes);
        $this->assertTrue($entry->billable);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_ENTRY,
            'external_id' => 'toggl:111',
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_api_import_is_idempotent(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:30:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $from = CarbonImmutable::parse('2026-05-25');
        $to = CarbonImmutable::parse('2026-05-27');

        $first = $this->service()->importFromApi($this->organization, $config, $from, $to);
        $second = $this->service()->importFromApi($this->organization, $config, $from, $to);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_unmatched_entry_is_recorded_in_inbox(): void {
        $config = $this->enableToggl();

        $this->fakeApi(
            timeEntries: [[
                'id' => 222,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:15:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Unknown Co']],
            projects: [['id' => 9, 'name' => 'Mystery', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $result = $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertSame(0, TimeEntry::query()->count());

        // MVP-103 Phase 2b: unmatched landet in der universellen Inbox (gruppiert).
        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'external_id' => 'toggl:222',
            'group_key' => $this->groupKey('Unknown Co', 'Mystery'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    public function test_csv_import_records_pending_entry(): void {
        $config = $this->enableToggl();

        $csv = <<<'CSV'
        User,Email,Client,Project,Task,Description,Billable,Start date,Start time,End date,End time,Duration,Tags
        Tech,tech@example.com,Beta GmbH,Intranet,,Wartung,Yes,2026-05-26,09:00:00,2026-05-26,10:00:00,01:00:00,
        CSV;

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'csv',
            'group_key' => $this->groupKey('Beta GmbH', 'Intranet'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    public function test_book_inbox_group_materializes_entries_and_remembers_reference(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Beta GmbH', 'Intranet');
        $customer = $project->customer;

        $item = $this->seedInboxEntry('Beta GmbH', 'Intranet', 'csv:abc', '2026-05-26 09:00:00', '2026-05-26 10:00:00');

        $result = $this->service()->bookInboxGroup($this->organization, $this->groupKey('Beta GmbH', 'Intranet'), $customer, $project);

        $this->assertSame(1, $result['created']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(60, $entry->minutes);

        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_CREATED, $item->fresh()->status);
        $this->assertSame($entry->id, $item->fresh()->resolved_to_id);

        // Reference gemerkt → künftiger Match.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'external_id' => 'Beta GmbH',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertNotNull($this->service()->matchProject(
            $this->organization,
            new \App\Plugins\Toggl\Sources\TogglEntry(
                source: 'csv',
                entryKey: 'csv:x',
                clientName: 'Beta GmbH',
                projectName: 'Intranet',
                description: null,
                startedAt: CarbonImmutable::now(),
                endedAt: CarbonImmutable::now(),
            ),
        ));
    }

    public function test_match_project_prefers_stable_toggl_id_over_name(): void {
        $project = $this->customerWithProject('Acme', 'Altname');

        // Stabile Projekt-ID-Referenz (wie sie API-Import/Backfill schreiben).
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => '777',
        ]);

        // In Toggl umbenannt: der Name passt nicht mehr, die ID schon → trifft trotzdem.
        $matched = $this->service()->matchProject(
            $this->organization,
            new TogglEntry(
                source: 'api', entryKey: 'toggl:1', clientName: 'Acme', projectName: 'Komplett anderer Name',
                description: null, startedAt: CarbonImmutable::now(), endedAt: CarbonImmutable::now(),
                projectId: 777,
            ),
        );

        $this->assertNotNull($matched);
        $this->assertSame($project->id, $matched->id);
    }

    public function test_book_inbox_group_remembers_stable_id_references(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Gamma', 'App');
        $customer = $project->customer;

        // Inbox-Eintrag mit Toggl-IDs im Snapshot (API-Herkunft).
        IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'api',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'toggl:42',
            'dedupe_key' => 'entry:toggl:42',
            'group_key' => $this->groupKey('Gamma', 'App'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => 'api', 'entry_key' => 'toggl:42',
                'client_name' => 'Gamma', 'project_name' => 'App',
                'client_id' => 5, 'project_id' => 50,
                'description' => null,
                'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00')->toIso8601String(),
                'ended_at' => CarbonImmutable::parse('2026-05-26 10:00:00')->toIso8601String(),
                'billable' => false, 'user_email' => null,
            ],
            'display_title' => 'App',
            'display_subtitle' => 'Gamma',
            'occurred_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
        ]);

        $this->service()->bookInboxGroup($this->organization, $this->groupKey('Gamma', 'App'), $customer, $project);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT_ID,
            'external_id' => '50',
            'referenceable_id' => $project->id,
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT_ID,
            'external_id' => '5',
            'referenceable_id' => $customer->id,
        ]);
    }

    public function test_backfill_writes_id_references_for_existing_name_links(): void {
        // Toggl aktiviert mit fixem Workspace (kein /workspaces-Aufruf nötig).
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'test-token', 'workspace_id' => 100],
        ]);

        // Bestehende, namensbasiert verknüpfte Datensätze.
        $project = $this->customerWithProject('Acme', 'Website');
        $customer = $project->customer;
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id,
            'external_id' => 'Acme',
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT,
            'referenceable_type' => $project->getMorphClass(), 'referenceable_id' => $project->id,
            'external_id' => 'acme|website',
        ]);

        Http::fake([
            'https://api.track.toggl.com/api/v9/workspaces/100/clients*' => Http::response([
                ['id' => 1, 'name' => 'Acme', 'archived' => false],
            ]),
            'https://api.track.toggl.com/api/v9/workspaces/100/projects*' => Http::response([
                ['id' => 10, 'name' => 'Website', 'client_id' => 1, 'active' => true],
            ]),
        ]);

        $result = $this->service()->backfillIdReferences($this->organization);

        $this->assertSame(1, $result['projects']);
        $this->assertSame(1, $result['clients']);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT_ID,
            'external_id' => '1',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT_ID,
            'external_id' => '10',
            'referenceable_id' => $project->id,
        ]);
    }

    public function test_suggest_customer_and_project_return_close_matches(): void {
        $project = $this->customerWithProject('Acme GmbH', 'Webseite Relaunch');

        // Leicht abweichende Toggl-Schreibweise → Fuzzy-Vorschlag greift.
        $customer = $this->service()->suggestCustomer($this->organization, 'Acme Gmbh');
        $this->assertNotNull($customer);
        $this->assertSame($project->customer_id, $customer->id);

        $suggested = $this->service()->suggestProject($this->organization, $customer, 'Webseite-Relaunch');
        $this->assertNotNull($suggested);
        $this->assertSame($project->id, $suggested->id);

        // Komplett fremder Name → kein Vorschlag.
        $this->assertNull($this->service()->suggestCustomer($this->organization, 'Völlig anderer Laden'));
    }

    public function test_book_group_creates_new_customer_and_project_when_requested(): void {
        $this->enableToggl();
        $this->seedInboxEntry('Neukunde AG', 'Migration', 'csv:new', '2026-05-26 09:00:00', '2026-05-26 10:30:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('Neukunde AG', 'Migration'),
                'customer_mode' => 'new',
                'new_customer_name' => 'Neukunde AG',
                'project_mode' => 'new',
                'new_project_name' => 'Migration',
            ])
            ->assertRedirect();

        $customer = Customer::query()->where('name', 'Neukunde AG')->first();
        $this->assertNotNull($customer);

        $project = Project::query()->where('name', 'Migration')->where('customer_id', $customer->id)->first();
        $this->assertNotNull($project);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);

        // Referenzen gemerkt → künftiger Import matcht automatisch.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'external_id' => 'Neukunde AG',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('integration_inbox_items', [
            'external_id' => 'csv:new',
            'status' => IntegrationInboxItem::STATUS_RESOLVED_CREATED,
        ]);
    }

    public function test_book_group_to_existing_customer_with_new_project(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bestand GmbH',
        ]);

        $this->seedInboxEntry('Bestand GmbH', 'Support 2026', 'csv:exist', '2026-05-26 09:00:00', '2026-05-26 10:00:00', false);

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('Bestand GmbH', 'Support 2026'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'project_mode' => 'new',
                'new_project_name' => 'Support 2026',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Support 2026')->where('customer_id', $customer->id)->first();
        $this->assertNotNull($project);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count());
    }

    public function test_update_and_delete_mapping(): void {
        $beta = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta']);
        $gamma = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gamma']);

        $ref = ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $beta->getMorphClass(),
            'referenceable_id' => $beta->id,
            'external_id' => 'Beta Client',
            'synced_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.update', $ref->id), ['target_id' => $gamma->sqid])
            ->assertRedirect();

        $this->assertDatabaseHas('external_references', [
            'id' => $ref->id,
            'referenceable_id' => $gamma->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.delete', $ref->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('external_references', ['id' => $ref->id]);
    }

    public function test_non_billing_user_cannot_book_group(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => 'x|y',
                'customer_mode' => 'new',
                'new_customer_name' => 'X',
                'project_mode' => 'new',
                'new_project_name' => 'Y',
            ])
            ->assertForbidden();
    }
}
