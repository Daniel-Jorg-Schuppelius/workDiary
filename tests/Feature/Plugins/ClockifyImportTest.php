<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Clockify\{ClockifyConfig, ClockifyImportService, ClockifyPlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Clockify-CSV-Import: Detailed-Report-Header (inkl. Währungs-Präfix-Spalten,
 * Teilmengen, Datums-/Zeitformat-Varianten) über die gemeinsame
 * Matching-Pipeline.
 */
class ClockifyImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    /** @return array<string, mixed> */
    private function enableClockify(): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => ClockifyPlugin::ID,
            'enabled' => true,
            // Parser-/Projekt-Semantik-Tests mit Fixtures ohne auflösbare
            // Benutzer-E-Mail — Einbenutzer-Modus (MVP-509) hält den
            // Standard-Benutzer-Fallback aktiv; Mehrbenutzer-Semantik der
            // gemeinsamen Basis deckt TogglUserResolutionTest ab.
            'settings' => ['default_billable' => true, 'single_user_mode' => true],
        ]);

        return ClockifyConfig::resolve($this->organization->id);
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

    public function test_full_detailed_report_header_with_currency_columns(): void {
        $config = $this->enableClockify();
        $project = $this->customerWithProject('Acme', 'Website');

        // Voller Detailed-Report-Header inkl. Währungs-Präfix-Spalten (EUR).
        $csv = <<<'CSV'
        Project,Client,Description,Task,User,Group,Email,Tags,Billable,Start Date,Start Time,End Date,End Time,Duration (h),Duration (decimal),Billable Rate (EUR),Billable Amount (EUR)
        Website,Acme,Feature,Development,Daniel,,daniel@example.com,frontend,Yes,2026-06-01,09:00:00,2026-06-01,10:30:00,01:30:00,1.50,80.00,120.00
        CSV;

        $result = (new ClockifyImportService)->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['unmatched']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);
        $this->assertTrue($entry->billable);
        $this->assertSame('Website — Development — Feature', $entry->description);
    }

    public function test_us_date_format_and_am_pm_times(): void {
        $config = $this->enableClockify();
        $project = $this->customerWithProject('Acme', 'Website');

        // US-Profil: MM/DD/YYYY + 12-Stunden-Zeit.
        $csv = <<<'CSV'
        Project,Client,Billable,Start Date,Start Time,End Date,End Time
        Website,Acme,No,06/01/2026,9:00:00 AM,06/01/2026,10:30:00 AM
        CSV;

        $result = (new ClockifyImportService)->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame('2026-06-01', $entry->date?->toDateString());
        $this->assertSame(90, $entry->minutes);
        $this->assertFalse($entry->billable);
    }

    public function test_day_first_date_detected_and_duration_fallback(): void {
        $config = $this->enableClockify();
        $project = $this->customerWithProject('Acme', 'Website');

        // Tag > 12 → eindeutig DD/MM/YYYY; keine End-Spalten → Duration (h).
        $csv = <<<'CSV'
        Project,Client,Start Date,Start Time,Duration (h)
        Website,Acme,25/06/2026,08:00:00,02:00:00
        CSV;

        $result = (new ClockifyImportService)->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame('2026-06-25', $entry->date?->toDateString());
        $this->assertSame(120, $entry->minutes);
    }

    public function test_reimport_is_idempotent_and_unmatched_goes_to_inbox(): void {
        $config = $this->enableClockify();

        $csv = <<<'CSV'
        Project,Client,Start Date,Start Time,Duration (decimal)
        Fremdprojekt,Unbekannt,2026-06-01,09:00:00,1.5
        CSV;

        $service = new ClockifyImportService;
        $first = $service->importFromCsv($this->organization, $csv, $config);
        $this->assertSame(1, $first['unmatched']);

        // Wiederholter Import erzeugt kein zweites Inbox-Item (Dedupe-Key).
        $second = $service->importFromCsv($this->organization, $csv, $config);
        $this->assertSame(1, $second['unmatched']);
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', ClockifyPlugin::ID)->count());

        // Buchung der Gruppe merkt die Referenz → dritter Import matcht automatisch.
        $project = $this->customerWithProject('Unbekannt', 'Fremdprojekt');
        $groups = $service->openInboxGroups($this->organization);
        $this->assertCount(1, $groups);
        $groupKey = (string) ($groups->first()['group_key'] ?? '');

        $booked = $service->bookInboxGroup($this->organization, $groupKey, $project->customer, $project);
        $this->assertSame(1, $booked['created']);

        $third = $service->importFromCsv($this->organization, $csv, $config);
        $this->assertSame(0, $third['created']);
        $this->assertSame(1, $third['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }
}
