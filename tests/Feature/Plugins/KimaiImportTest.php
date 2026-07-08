<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Kimai\{KimaiConfig, KimaiImportService, KimaiPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class KimaiImportTest extends TestCase {
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

    private function service(): KimaiImportService {
        return new KimaiImportService;
    }

    private function enableKimai(): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'enabled' => true,
            'settings' => ['default_billable' => true],
        ]);

        return KimaiConfig::resolve($this->organization->id);
    }

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

    /** group_key wie der Import ihn erzeugt: lower(client|project|activity). */
    private function groupKey(string $client, string $project, string $activity = ''): string {
        $parts = [trim($client), trim($project)];
        if (trim($activity) !== '') {
            $parts[] = trim($activity);
        }

        return mb_strtolower(implode('|', $parts));
    }

    public function test_english_header_matched_project_creates_time_entry(): void {
        $config = $this->enableKimai();
        $project = $this->customerWithProject('Acme', 'Website');

        // Kimai-EN-Export: Datum + Von + Dauer als H:MM (90 Minuten, NICHT 90 Sekunden).
        $csv = <<<'CSV'
        Date,From,Duration,Customer,Project,Activity,Description,Billable,Tags
        2026-05-26,09:00,1:30,Acme,Website,Development,Feature,1,frontend
        CSV;

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['unmatched']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes, 'H:MM-Dauer 1:30 muss 90 Minuten ergeben.');
        $this->assertTrue($entry->billable);
        $this->assertSame('Website — Development — Feature', $entry->description);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_ENTRY,
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_import_is_idempotent_on_reimport(): void {
        $config = $this->enableKimai();
        $this->customerWithProject('Acme', 'Website');

        $csv = <<<'CSV'
        Date,From,Duration,Customer,Project,Activity,Description,Billable
        2026-05-26,09:00,0:45,Acme,Website,Development,Bugfix,1
        CSV;

        $first = $this->service()->importFromCsv($this->organization, $csv, $config);
        $second = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_decimal_duration_is_parsed_as_hours(): void {
        $config = $this->enableKimai();
        $project = $this->customerWithProject('Acme', 'Website');

        // Kimai „Dezimal-Export": 1.5 Stunden = 90 Minuten.
        $csv = <<<'CSV'
        Date,From,Duration,Customer,Project,Activity,Billable
        2026-05-26,09:00,1.5,Acme,Website,Development,0
        CSV;

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);
        $this->assertFalse($entry->billable, 'Billable=0 darf nicht abrechenbar sein.');
    }

    public function test_midnight_rollover_uses_next_day_for_end(): void {
        $config = $this->enableKimai();
        $project = $this->customerWithProject('Acme', 'Website');

        // Von 23:00 bis 00:30 → Ende am Folgetag (90 Minuten).
        $csv = <<<'CSV'
        Datum,Von,Bis,Kunde,Projekt,Tätigkeit,Abrechenbar
        2026-05-26,23:00,00:30,Acme,Website,Support,ja
        CSV;

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);
        $this->assertSame('2026-05-27', CarbonImmutable::parse($entry->ended_at)->toDateString());
    }

    public function test_german_header_unmatched_entry_lands_in_inbox_grouped_by_activity(): void {
        $config = $this->enableKimai();

        // Semikolon-getrennt (Kimai-DE-Option), unbekannter Kunde/Projekt.
        $csv = <<<'CSV'
        Datum;Von;Dauer;Kunde;Projekt;Tätigkeit;Beschreibung;Abrechenbar
        2026-05-26;09:00;1:00;Beta GmbH;Intranet;Wartung;Update;ja
        CSV;

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertSame(0, TimeEntry::query()->count());

        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'source' => 'csv',
            'group_key' => $this->groupKey('Beta GmbH', 'Intranet', 'Wartung'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    public function test_different_activities_form_separate_inbox_groups(): void {
        $config = $this->enableKimai();

        $csv = <<<'CSV'
        Datum,Von,Dauer,Kunde,Projekt,Tätigkeit,Abrechenbar
        2026-05-26,09:00,1:00,Beta GmbH,Intranet,Wartung,ja
        2026-05-26,11:00,0:30,Beta GmbH,Intranet,Entwicklung,ja
        CSV;

        $this->service()->importFromCsv($this->organization, $csv, $config);

        // Tätigkeit fließt in den group_key → zwei getrennte Gruppen.
        $this->assertSame(2, $this->service()->openInboxGroups($this->organization)->count());
    }

    public function test_book_inbox_group_via_registered_group_booker_route(): void {
        $this->enableKimai();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bestand GmbH',
        ]);

        $csv = <<<'CSV'
        Datum,Von,Dauer,Kunde,Projekt,Tätigkeit,Abrechenbar
        2026-05-26,09:00,1:00,Bestand GmbH,Support 2026,Wartung,ja
        CSV;
        $this->service()->importFromCsv($this->organization, $csv, KimaiConfig::resolve($this->organization->id));

        // Buchung über die universelle Inbox-Route beweist den Registry-Eintrag 'kimai'.
        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => KimaiPlugin::ID,
                'group_key' => $this->groupKey('Bestand GmbH', 'Support 2026', 'Wartung'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'project_mode' => 'new',
                'new_project_name' => 'Support 2026',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Support 2026')->where('customer_id', $customer->id)->first();
        $this->assertNotNull($project);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count());

        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => KimaiPlugin::ID,
            'status' => IntegrationInboxItem::STATUS_RESOLVED_CREATED,
        ]);

        // Projekt-Referenz gemerkt → Folgeimport matcht automatisch.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_PROJECT,
            'external_id' => $this->groupKey('Bestand GmbH', 'Support 2026'),
            'referenceable_id' => $project->id,
        ]);
    }

    public function test_suggest_customer_and_project_return_close_matches(): void {
        $project = $this->customerWithProject('Acme GmbH', 'Webseite Relaunch');

        $customer = $this->service()->suggestCustomer($this->organization, 'Acme Gmbh');
        $this->assertNotNull($customer);
        $this->assertSame($project->customer_id, $customer->id);

        $suggested = $this->service()->suggestProject($this->organization, $customer, 'Webseite-Relaunch');
        $this->assertNotNull($suggested);
        $this->assertSame($project->id, $suggested->id);

        $this->assertNull($this->service()->suggestCustomer($this->organization, 'Völlig anderer Laden'));
    }
}
