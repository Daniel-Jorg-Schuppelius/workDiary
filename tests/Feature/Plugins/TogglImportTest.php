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

use App\Models\{Customer, ExternalReference, PluginSetting, Project, TimeEntry, TogglPendingEntry, User};
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

        $this->assertDatabaseHas('toggl_pending_entries', [
            'organization_id' => $this->organization->id,
            'client_name' => 'Unknown Co',
            'project_name' => 'Mystery',
            'entry_key' => 'toggl:222',
            'status' => TogglPendingEntry::STATUS_OPEN,
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
        $this->assertDatabaseHas('toggl_pending_entries', [
            'organization_id' => $this->organization->id,
            'source' => TogglPendingEntry::SOURCE_CSV,
            'client_name' => 'Beta GmbH',
            'project_name' => 'Intranet',
            'status' => TogglPendingEntry::STATUS_OPEN,
        ]);
    }

    public function test_assign_pending_materializes_entries_and_remembers_reference(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Beta GmbH', 'Intranet');
        $customer = $project->customer;

        TogglPendingEntry::query()->create([
            'organization_id' => $this->organization->id,
            'source' => TogglPendingEntry::SOURCE_CSV,
            'entry_key' => 'csv:abc',
            'client_name' => 'Beta GmbH',
            'project_name' => 'Intranet',
            'description' => 'Wartung',
            'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'billable' => true,
            'status' => TogglPendingEntry::STATUS_OPEN,
        ]);

        $result = $this->service()->assignPending($this->organization, 'Beta GmbH', 'Intranet', $customer, $project);

        $this->assertSame(1, $result['created']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(60, $entry->minutes);

        $this->assertDatabaseHas('toggl_pending_entries', [
            'entry_key' => 'csv:abc',
            'status' => TogglPendingEntry::STATUS_IMPORTED,
            'time_entry_id' => $entry->id,
        ]);

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

    public function test_assign_creates_new_customer_and_project_when_requested(): void {
        TogglPendingEntry::query()->create([
            'organization_id' => $this->organization->id,
            'source' => TogglPendingEntry::SOURCE_CSV,
            'entry_key' => 'csv:new',
            'client_name' => 'Neukunde AG',
            'project_name' => 'Migration',
            'description' => 'Setup',
            'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:30:00'),
            'billable' => true,
            'status' => TogglPendingEntry::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.pending.assign'), [
                'client_name' => 'Neukunde AG',
                'project_name' => 'Migration',
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
        $this->assertDatabaseHas('toggl_pending_entries', [
            'entry_key' => 'csv:new',
            'status' => TogglPendingEntry::STATUS_IMPORTED,
        ]);
    }

    public function test_assign_to_existing_customer_with_new_project(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bestand GmbH',
        ]);

        TogglPendingEntry::query()->create([
            'organization_id' => $this->organization->id,
            'source' => TogglPendingEntry::SOURCE_CSV,
            'entry_key' => 'csv:exist',
            'client_name' => 'Bestand GmbH',
            'project_name' => 'Support 2026',
            'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'billable' => false,
            'status' => TogglPendingEntry::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.pending.assign'), [
                'client_name' => 'Bestand GmbH',
                'project_name' => 'Support 2026',
                'customer_mode' => 'existing',
                'customer_id' => $customer->sqid,
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

    public function test_non_admin_cannot_assign(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.toggl.pending.assign'), [
                'customer_mode' => 'new',
                'new_customer_name' => 'X',
                'project_mode' => 'new',
                'new_project_name' => 'Y',
            ])
            ->assertForbidden();
    }
}
