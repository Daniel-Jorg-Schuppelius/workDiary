<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglExportImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ForeignCustomer, Project, TimeEntry, User};
use App\Plugins\Toggl\TogglExportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TogglExportImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $base;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $this->base = sys_get_temp_dir() . '/toggl_export_' . uniqid();
        $this->buildOwn($this->base . '/OwnWs');
        $this->buildCustomer($this->base . '/BigCorp');
    }

    protected function tearDown(): void {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function modes(): array {
        return [
            'OwnWs' => ['mode' => TogglExportImporter::MODE_OWN],
            'BigCorp' => ['mode' => TogglExportImporter::MODE_CUSTOMER, 'customer_name' => 'BigCorp'],
        ];
    }

    public function test_dry_run_reports_counts_without_persisting(): void {
        $result = (new TogglExportImporter)->import(
            $this->base,
            $this->organization,
            $this->modes(),
            TogglExportImporter::USER_PER_EMAIL,
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertSame(2, $result['totals']['customers_created']); // Acme + BigCorp
        $this->assertSame(1, $result['totals']['foreign_customers_created']); // Internal (unter BigCorp)
        $this->assertSame(2, $result['totals']['projects_created']);  // Website + Rollout
        $this->assertSame(1, $result['totals']['users_created']);     // dev@ (einmal)
        $this->assertSame(2, $result['totals']['entries_created']);

        // Nichts gespeichert.
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_import_creates_and_maps_correctly(): void {
        (new TogglExportImporter)->import(
            $this->base,
            $this->organization,
            $this->modes(),
            TogglExportImporter::USER_PER_EMAIL,
            dryRun: false,
        );

        $acme = Customer::query()->where('name', 'Acme')->first();
        $bigcorp = Customer::query()->where('name', 'BigCorp')->first();
        $this->assertNotNull($acme);
        $this->assertNotNull($bigcorp);
        // Der interne Toggl-Client "Internal" wird NICHT als Kunde, sondern als
        // Fremdkunde unter der Firma "BigCorp" angelegt.
        $this->assertNull(Customer::query()->where('name', 'Internal')->first());
        $internal = ForeignCustomer::query()->where('name', 'Internal')->first();
        $this->assertNotNull($internal);
        $this->assertSame($bigcorp->id, $internal->customer_id);

        $website = Project::query()->where('name', 'Website')->first();
        // Kunden-Workspace: Projektname unverändert, Verknüpfung über foreign_customer_id.
        $rollout = Project::query()->where('name', 'Rollout')->first();
        $this->assertNotNull($rollout);
        $this->assertSame($acme->id, $website->customer_id);
        $this->assertSame($bigcorp->id, $rollout->customer_id);
        $this->assertSame($internal->id, $rollout->foreign_customer_id);

        $dev = User::query()->where('email', 'dev@example.com')->first();
        $this->assertNotNull($dev);

        $this->assertSame(2, TimeEntry::query()->count());
        $this->assertSame(2, TimeEntry::query()->where('user_id', $dev->id)->count());
        $this->assertSame(60, TimeEntry::query()->where('project_id', $website->id)->first()->minutes);
    }

    public function test_existing_customer_is_reused_not_duplicated(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme']);

        $result = (new TogglExportImporter)->import(
            $this->base,
            $this->organization,
            ['OwnWs' => ['mode' => TogglExportImporter::MODE_OWN]],
            TogglExportImporter::USER_PER_EMAIL,
            dryRun: false,
        );

        $this->assertSame(1, Customer::query()->where('name', 'Acme')->count());
        $this->assertSame(1, $result['workspaces'][0]['customers_reused']);
    }

    public function test_same_project_name_across_end_customers_stays_separate(): void {
        $dir = $this->base . '/MultiEnd';
        @mkdir($dir, 0777, true);
        file_put_contents($dir . '/clients.json', json_encode([
            ['id' => 1, 'name' => 'Kunde A', 'archived' => false],
            ['id' => 2, 'name' => 'Kunde B', 'archived' => false],
        ]));
        file_put_contents($dir . '/projects.json', json_encode([
            ['id' => 10, 'name' => 'DATEV', 'client_id' => 1, 'client_name' => 'Kunde A', 'active' => true],
            ['id' => 11, 'name' => 'DATEV', 'client_id' => 2, 'client_name' => 'Kunde B', 'active' => true],
        ]));
        file_put_contents($dir . '/workspace_users.json', json_encode([]));
        $this->writeCsv($dir . '/Toggl_time_entries_2025-01-01_to_2025-12-31.csv', [
            ['Dev', 'dev@example.com', 'Kunde A', 'DATEV', '', 'A', 'No', '2025-03-01', '09:00:00', '2025-03-01', '10:00:00', '01:00:00', ''],
            ['Dev', 'dev@example.com', 'Kunde B', 'DATEV', '', 'B', 'No', '2025-03-02', '09:00:00', '2025-03-02', '10:00:00', '01:00:00', ''],
        ]);

        (new TogglExportImporter)->import(
            $this->base,
            $this->organization,
            ['MultiEnd' => ['mode' => TogglExportImporter::MODE_CUSTOMER, 'customer_name' => 'Acme Holding']],
            TogglExportImporter::USER_SINGLE,
            dryRun: false,
        );

        $holding = Customer::query()->where('name', 'Acme Holding')->first();
        $this->assertNotNull($holding);
        // Zwei Fremdkunden, zwei eigenständige "DATEV"-Projekte (nicht gemerged).
        $this->assertSame(2, ForeignCustomer::query()->where('customer_id', $holding->id)->count());
        $this->assertSame(2, Project::query()->where('customer_id', $holding->id)->where('name', 'DATEV')->count());
        $this->assertSame(2, TimeEntry::query()->whereIn('project_id', Project::query()->where('customer_id', $holding->id)->pluck('id'))->count());
    }

    public function test_import_is_idempotent(): void {
        $importer = new TogglExportImporter;
        $importer->import($this->base, $this->organization, $this->modes(), TogglExportImporter::USER_PER_EMAIL, dryRun: false);
        $second = $importer->import($this->base, $this->organization, $this->modes(), TogglExportImporter::USER_PER_EMAIL, dryRun: false);

        $this->assertSame(2, TimeEntry::query()->count());
        $this->assertSame(2, $second['totals']['entries_skipped']);
        $this->assertSame(0, $second['totals']['entries_created']);
    }

    public function test_explicit_customer_id_uses_selected_customer(): void {
        $selected = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Selected Corp']);

        $result = (new TogglExportImporter)->import(
            $this->base,
            $this->organization,
            ['BigCorp' => ['mode' => TogglExportImporter::MODE_CUSTOMER, 'customer_id' => $selected->id]],
            TogglExportImporter::USER_SINGLE,
            dryRun: false,
        );

        // Kein neuer "BigCorp"-Kunde — alles hängt unter dem gewählten Kunden.
        $this->assertNull(Customer::query()->where('name', 'BigCorp')->first());
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, $result['workspaces'][0]['customers_reused']);
        $this->assertSame(0, $result['workspaces'][0]['customers_created']);

        $rollout = Project::query()->where('name', 'Rollout')->first();
        $internal = ForeignCustomer::query()->where('name', 'Internal')->first();
        $this->assertSame($selected->id, $rollout->customer_id);
        $this->assertSame($selected->id, $internal->customer_id);
    }

    public function test_explicit_user_map_assigns_entries_to_mapped_user(): void {
        $mapped = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'mapped@example.com', 'name' => 'Mapped']);

        (new TogglExportImporter)->import(
            $this->base,
            $this->organization,
            ['OwnWs' => ['mode' => TogglExportImporter::MODE_OWN]],
            TogglExportImporter::USER_PER_EMAIL,
            dryRun: false,
            userMap: ['dev@example.com' => $mapped->id],
        );

        // dev@ wird NICHT neu angelegt; der Eintrag läuft auf den zugeordneten Benutzer.
        $this->assertNull(User::query()->where('email', 'dev@example.com')->first());
        $website = Project::query()->where('name', 'Website')->first();
        $entry = TimeEntry::query()->where('project_id', $website->id)->first();
        $this->assertSame($mapped->id, $entry->user_id);
    }

    public function test_long_description_is_stored_in_full(): void {
        // Historische Toggl-Notizen können > 500 Zeichen lang sein. Nach dem
        // Verbreitern der Spalte auf TEXT dürfen sie vollständig gespeichert
        // werden, statt den Import abzubrechen oder gekappt zu werden.
        $dir = sys_get_temp_dir() . '/toggl_long_' . uniqid();
        $long = trim(str_repeat('Lange Besprechungsnotiz mit vielen Details -> ', 30)); // ~1.4k Zeichen
        @mkdir($dir . '/LongWs', 0777, true);
        file_put_contents($dir . '/LongWs/clients.json', json_encode([
            ['id' => 1, 'name' => 'Acme', 'archived' => false],
        ]));
        file_put_contents($dir . '/LongWs/projects.json', json_encode([
            ['id' => 10, 'name' => 'Website', 'client_id' => 1, 'client_name' => 'Acme', 'active' => true],
        ]));
        file_put_contents($dir . '/LongWs/workspace_users.json', json_encode([
            ['id' => 1, 'email' => 'dev@example.com', 'name' => 'Dev'],
        ]));
        $this->writeCsv($dir . '/LongWs/Toggl_time_entries_2025-01-01_to_2025-12-31.csv', [
            ['Dev', 'dev@example.com', 'Acme', 'Website', '', $long, 'No', '2025-03-02', '09:00:00', '2025-03-02', '10:00:00', '01:00:00', ''],
        ]);

        (new TogglExportImporter)->import(
            $dir,
            $this->organization,
            ['LongWs' => ['mode' => TogglExportImporter::MODE_OWN]],
            TogglExportImporter::USER_PER_EMAIL,
            dryRun: false,
        );

        $entry = TimeEntry::query()->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertGreaterThan(500, mb_strlen((string) $entry->description));
        $this->assertStringContainsString('Besprechungsnotiz', (string) $entry->description);

        $this->rrmdir($dir);
    }

    public function test_zip_upload_extracts_and_detects_workspaces(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $zipPath = sys_get_temp_dir() . '/toggl_upload_' . uniqid() . '.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('AcmeWs/clients.json', (string) json_encode([['id' => 1, 'name' => 'Acme', 'archived' => false]]));
        $zip->addFromString('AcmeWs/projects.json', (string) json_encode([['id' => 10, 'name' => 'Website', 'client_id' => 1, 'client_name' => 'Acme', 'active' => true]]));
        $zip->addFromString('AcmeWs/workspace_users.json', (string) json_encode([['id' => 1, 'email' => 'dev@example.com', 'name' => 'Dev']]));
        $zip->close();

        $file = new \Illuminate\Http\UploadedFile($zipPath, 'export.zip', 'application/zip', null, true);

        $response = $this->actingAs($admin)->post(route('admin.toggl.import-export.upload'), ['archive' => $file]);
        $response->assertRedirect();

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertArrayHasKey('path', $q);
        $this->assertDirectoryExists($q['path']);
        $this->assertContains('AcmeWs', \App\Plugins\Toggl\Sources\TogglWorkspaceReader::detectWorkspaces($q['path']));

        $this->actingAs($admin)->get(route('admin.toggl.import-export', ['path' => $q['path']]))
            ->assertOk()
            ->assertSee('AcmeWs');

        @unlink($zipPath);
        $this->rrmdir($q['path']);
    }

    public function test_reset_clears_preview_summary(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->withSession(['toggl_export_summary' => ['dry_run' => true, 'workspaces' => [], 'totals' => []]])
            ->post(route('admin.toggl.import-export.reset'))
            ->assertRedirect();

        $this->actingAs($admin)->get(route('admin.toggl.import-export'))
            ->assertOk()
            ->assertDontSee(__('Vorschau (nicht gespeichert)'));
    }

    // --- Fixtures -----------------------------------------------------------

    private function buildOwn(string $dir): void {
        @mkdir($dir, 0777, true);
        file_put_contents($dir . '/clients.json', json_encode([
            ['id' => 1, 'name' => 'Acme', 'archived' => false],
        ]));
        file_put_contents($dir . '/projects.json', json_encode([
            ['id' => 10, 'name' => 'Website', 'client_id' => 1, 'client_name' => 'Acme', 'color' => '#112233', 'billable' => false, 'active' => true, 'start_date' => '2020-01-01'],
        ]));
        file_put_contents($dir . '/workspace_users.json', json_encode([
            ['id' => 1, 'email' => 'dev@example.com', 'name' => 'Dev', 'timezone' => 'Europe/Berlin'],
        ]));
        $this->writeCsv($dir . '/Toggl_time_entries_2025-01-01_to_2025-12-31.csv', [
            ['Dev', 'dev@example.com', 'Acme', 'Website', '', 'Arbeit', 'No', '2025-01-02', '09:00:00', '2025-01-02', '10:00:00', '01:00:00', ''],
        ]);
    }

    private function buildCustomer(string $dir): void {
        @mkdir($dir, 0777, true);
        file_put_contents($dir . '/clients.json', json_encode([
            ['id' => 2, 'name' => 'Internal', 'archived' => false],
        ]));
        file_put_contents($dir . '/projects.json', json_encode([
            ['id' => 20, 'name' => 'Rollout', 'client_id' => 2, 'client_name' => 'Internal', 'billable' => true, 'active' => true],
        ]));
        file_put_contents($dir . '/workspace_users.json', json_encode([
            ['id' => 1, 'email' => 'dev@example.com', 'name' => 'Dev'],
        ]));
        $this->writeCsv($dir . '/Toggl_time_entries_2025-01-01_to_2025-12-31.csv', [
            ['Dev', 'dev@example.com', 'Internal', 'Rollout', '', 'Kram', 'Yes', '2025-02-02', '09:00:00', '2025-02-02', '11:00:00', '02:00:00', ''],
        ]);
    }

    /** @param array<int, array<int, string>> $rows */
    private function writeCsv(string $path, array $rows): void {
        $header = ['User', 'Email', 'Client', 'Project', 'Task', 'Description', 'Billable', 'Start date', 'Start time', 'End date', 'End time', 'Duration', 'Tags'];
        $lines = [$this->csvLine($header)];
        foreach ($rows as $row) {
            $lines[] = $this->csvLine($row);
        }
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    /** @param array<int, string> $fields */
    private function csvLine(array $fields): string {
        return implode(',', array_map(static fn(string $f): string => '"' . str_replace('"', '""', $f) . '"', $fields));
    }

    private function rrmdir(string $dir): void {
        if (! is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
