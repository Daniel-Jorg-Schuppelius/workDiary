<?php
/*
 * Created on   : Tue Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglApiWorkspaceImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ForeignCustomer, Project, TimeEntry, User};
use App\Plugins\Toggl\Sources\{ApiWorkspaceSource, TogglApiClient};
use App\Plugins\Toggl\TogglExportImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Deckt den Workspace-Import direkt aus der Toggl-API ab: Stammdaten aus der
 * Track-API v9, Zeiteinträge aus der Reports-API v3. Spiegelt die own/customer-
 * Semantik des Ordner-Imports ({@see TogglExportImportTest}).
 */
class TogglApiWorkspaceImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://api.track.toggl.com/api/v9';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $this->fakeApi();
    }

    private function client(): TogglApiClient {
        return new TogglApiClient('test-token', self::BASE);
    }

    /** @return array<string, ApiWorkspaceSource> */
    private function sources(): array {
        $from = CarbonImmutable::parse('2025-01-01');
        $to = CarbonImmutable::parse('2025-12-31');

        return [
            'OwnWs' => new ApiWorkspaceSource($this->client(), 100, $from, $to),
            'BigCorp' => new ApiWorkspaceSource($this->client(), 200, $from, $to),
        ];
    }

    /** @return array<string, array{mode: string, customer_name?: string}> */
    private function modes(): array {
        return [
            'OwnWs' => ['mode' => TogglExportImporter::MODE_OWN],
            'BigCorp' => ['mode' => TogglExportImporter::MODE_CUSTOMER, 'customer_name' => 'BigCorp'],
        ];
    }

    public function test_workspaces_are_listed_from_api(): void {
        $workspaces = $this->client()->workspaces();

        $this->assertCount(2, $workspaces);
        $this->assertSame('Own Workspace', $workspaces[0]['name']);
    }

    public function test_dry_run_reports_counts_without_persisting(): void {
        $result = (new TogglExportImporter)->importFromApi(
            $this->organization,
            $this->sources(),
            $this->modes(),
            TogglExportImporter::USER_PER_EMAIL_CREATE,
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertSame(2, $result['totals']['customers_created']);        // Acme + BigCorp
        $this->assertSame(1, $result['totals']['foreign_customers_created']); // Internal unter BigCorp
        $this->assertSame(2, $result['totals']['projects_created']);          // Website + Rollout
        $this->assertSame(2, $result['totals']['entries_created']);

        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_import_creates_and_maps_correctly(): void {
        (new TogglExportImporter)->importFromApi(
            $this->organization,
            $this->sources(),
            $this->modes(),
            TogglExportImporter::USER_PER_EMAIL_CREATE,
            dryRun: false,
        );

        $acme = Customer::query()->where('name', 'Acme')->first();
        $bigcorp = Customer::query()->where('name', 'BigCorp')->first();
        $this->assertNotNull($acme);
        $this->assertNotNull($bigcorp);

        // Interner Toggl-Client "Internal" → Fremdkunde unter BigCorp, kein Kunde.
        $this->assertNull(Customer::query()->where('name', 'Internal')->first());
        $internal = ForeignCustomer::query()->where('name', 'Internal')->first();
        $this->assertNotNull($internal);
        $this->assertSame($bigcorp->id, $internal->customer_id);

        $website = Project::query()->where('name', 'Website')->first();
        $rollout = Project::query()->where('name', 'Rollout')->first();
        $this->assertNotNull($website);
        $this->assertNotNull($rollout);
        $this->assertSame($acme->id, $website->customer_id);
        $this->assertNull($website->foreign_customer_id);
        $this->assertSame($bigcorp->id, $rollout->customer_id);
        $this->assertSame($internal->id, $rollout->foreign_customer_id);

        $dev = User::query()->where('email', 'dev@example.com')->first();
        $this->assertNotNull($dev);

        $this->assertSame(2, TimeEntry::query()->count());
        $this->assertSame(2, TimeEntry::query()->where('user_id', $dev->id)->count());
        $entry = TimeEntry::query()->where('project_id', $website->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(60, $entry->minutes);
    }

    public function test_import_is_idempotent(): void {
        $importer = new TogglExportImporter;
        $importer->importFromApi($this->organization, $this->sources(), $this->modes(), TogglExportImporter::USER_PER_EMAIL_CREATE, dryRun: false);
        $second = $importer->importFromApi($this->organization, $this->sources(), $this->modes(), TogglExportImporter::USER_PER_EMAIL_CREATE, dryRun: false);

        $this->assertSame(2, TimeEntry::query()->count());
        $this->assertSame(2, $second['totals']['entries_skipped']);
        $this->assertSame(0, $second['totals']['entries_created']);
    }

    private function fakeApi(): void {
        FakePluginHttp::fake([
            self::BASE . '/workspaces' => FakePluginHttp::response([
                ['id' => 100, 'name' => 'Own Workspace'],
                ['id' => 200, 'name' => 'BigCorp Workspace'],
            ]),

            // --- Workspace 100 (eigener Workspace) ---
            self::BASE . '/workspaces/100/clients*' => FakePluginHttp::response([
                ['id' => 1, 'name' => 'Acme', 'archived' => false],
            ]),
            self::BASE . '/workspaces/100/projects*' => FakePluginHttp::response([
                ['id' => 10, 'name' => 'Website', 'client_id' => 1, 'color' => '#112233', 'billable' => false, 'active' => true, 'start_date' => '2025-01-01'],
            ]),
            self::BASE . '/workspaces/100/users*' => FakePluginHttp::response([
                ['id' => 1, 'email' => 'dev@example.com', 'fullname' => 'Dev', 'timezone' => 'Europe/Berlin'],
            ]),
            'api.track.toggl.com/reports/api/v3/workspace/100/search/time_entries*' => FakePluginHttp::response([
                [
                    'project_id' => 10,
                    'description' => 'Arbeit',
                    'billable' => false,
                    'user_email' => 'dev@example.com',
                    'time_entries' => [
                        ['id' => 555, 'start' => '2025-01-02T09:00:00+00:00', 'stop' => '2025-01-02T10:00:00+00:00'],
                    ],
                ],
            ]),

            // --- Workspace 200 (als ein Kunde) ---
            self::BASE . '/workspaces/200/clients*' => FakePluginHttp::response([
                ['id' => 2, 'name' => 'Internal', 'archived' => false],
            ]),
            self::BASE . '/workspaces/200/projects*' => FakePluginHttp::response([
                ['id' => 20, 'name' => 'Rollout', 'client_id' => 2, 'billable' => true, 'active' => true, 'start_date' => '2025-02-01'],
            ]),
            self::BASE . '/workspaces/200/users*' => FakePluginHttp::response([
                ['id' => 1, 'email' => 'dev@example.com', 'fullname' => 'Dev'],
            ]),
            'api.track.toggl.com/reports/api/v3/workspace/200/search/time_entries*' => FakePluginHttp::response([
                [
                    'project_id' => 20,
                    'description' => 'Kram',
                    'billable' => true,
                    'user_email' => 'dev@example.com',
                    'time_entries' => [
                        ['id' => 777, 'start' => '2025-02-02T09:00:00+00:00', 'stop' => '2025-02-02T11:00:00+00:00'],
                    ],
                ],
            ]),
        ]);
    }
}
