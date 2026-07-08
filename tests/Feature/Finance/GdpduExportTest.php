<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GdpduExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\User\Permission;
use App\Models\{Customer, GobdExport, Invoice, InvoiceItem, Organization, Project, TimeEntry, User};
use App\Services\Finance\GdpduExportService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 063, MVP-132: GoBD-Z3-Datenträgerüberlassung. GDPdU-Paket
 * (index.xml + CSV), reproduzierbarer Paket-Hash, revisionssicherer Nachweis
 * (GobdExport + Audit), Mandantengrenze, Recht `finance.gobd.export`.
 */
final class GdpduExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private GdpduExportService $service;
    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->service = app(GdpduExportService::class);
        $this->accountant = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->accountant->givePermissionTo(Permission::FinanceGobdExport->value);
    }

    private function invoice(Organization $org, string $number, string $status = 'issued', string $issuedOn = '2025-06-15', ?string $customerName = null): Invoice {
        $customer = Customer::factory()->create(
            ['organization_id' => $org->id] + ($customerName !== null ? ['name' => $customerName] : []),
        );
        $invoice = Invoice::query()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => $status,
            'type' => 'invoice',
            'issued_on' => $issuedOn,
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_rate' => 19.00,
            'tax_amount' => 19.00,
            'total' => 119.00,
        ]);
        InvoiceItem::query()->create([
            'organization_id' => $org->id,
            'invoice_id' => $invoice->id,
            'description' => 'Beratung',
            'quantity' => 1,
            'unit' => 'Std',
            'unit_price' => 100.00,
            'amount' => 100.00,
            'position' => 1,
        ]);

        return $invoice;
    }

    private function timeEntry(
        Organization $org,
        string $customerName,
        string $projectName,
        string $personnelNumber = 'P-4711',
        string $date = '2025-06-10',
        int $minutes = 90,
        bool $billable = true,
        string $description = 'Vor-Ort-Beratung',
    ): TimeEntry {
        $user = User::factory()->user()->create(['organization_id' => $org->id, 'personnel_number' => $personnelNumber]);
        $customer = Customer::factory()->create(['organization_id' => $org->id, 'name' => $customerName]);
        $project = Project::factory()->create(['organization_id' => $org->id, 'customer_id' => $customer->id, 'name' => $projectName]);

        return TimeEntry::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'date' => $date,
            'minutes' => $minutes,
            'billable' => $billable,
            'description' => $description,
        ]);
    }

    /** @return array<string, string> */
    private function unzip(string $binary): array {
        $tmp = tempnam(sys_get_temp_dir(), 'gobdtest') . '.zip';
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $out[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        return $out;
    }

    public function test_build_produces_valid_gdpdu_package_with_proof(): void {
        $this->invoice($this->organization, 'RE-2025-001');

        $result = $this->service->build(
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['invoices', 'invoice_items', 'customers'],
            $this->accountant,
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['package_sha256']);
        $files = $this->unzip($result['content']);
        $this->assertArrayHasKey('index.xml', $files);
        $this->assertArrayHasKey('rechnungen.csv', $files);
        $this->assertStringContainsString('RE-2025-001', $files['rechnungen.csv']);
        $this->assertStringContainsString('119,00', $files['rechnungen.csv']); // Dezimalkomma

        // index.xml wohlgeformt + GDPdU-Grundgerüst.
        $xml = simplexml_load_string($files['index.xml']);
        $this->assertNotFalse($xml);
        $this->assertSame('DataSet', $xml->getName());
        $this->assertStringContainsString('Ausgangsrechnungen', $files['index.xml']);

        // Revisionssicherer Nachweis: GobdExport-Zeile + Audit.
        $this->assertDatabaseHas('gobd_exports', [
            'organization_id' => $this->organization->id,
            'package_sha256' => $result['package_sha256'],
            'record_count' => 3, // 1 Rechnung + 1 Position + 1 Debitor
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => (new GobdExport())->getMorphClass(),
            'event' => 'gobd.exported',
        ]);
    }

    public function test_package_hash_is_reproducible_for_same_period(): void {
        $this->invoice($this->organization, 'RE-2025-001');
        $args = [$this->organization, Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31'), ['invoices'], $this->accountant];

        $a = $this->service->build(...$args);
        $b = $this->service->build(...$args);

        $this->assertSame($a['package_sha256'], $b['package_sha256']);
    }

    public function test_export_respects_tenant_boundary(): void {
        $this->invoice($this->organization, 'RE-OWN');
        $otherOrg = Organization::factory()->create();
        $this->invoice($otherOrg, 'RE-FOREIGN');

        $result = $this->service->build(
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['invoices'],
            $this->accountant,
        );
        $files = $this->unzip($result['content']);

        $this->assertStringContainsString('RE-OWN', $files['rechnungen.csv']);
        $this->assertStringNotContainsString('RE-FOREIGN', $files['rechnungen.csv']);
    }

    public function test_preflight_warns_about_draft_invoices(): void {
        $this->invoice($this->organization, 'RE-DRAFT', status: 'draft');

        $preflight = $this->service->preflight($this->organization, Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31'));

        $this->assertNotEmpty($preflight['warnings']);
    }

    public function test_download_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('finance.gobd.index'))->assertForbidden();
        $this->actingAs($stranger)->post(route('finance.gobd.export'), ['from' => '2025-01-01', 'to' => '2025-12-31'])->assertForbidden();
    }

    public function test_export_encodings_convert_data_and_declare_charset(): void {
        $this->invoice($this->organization, 'RE-2025-050', customerName: 'Müller & Söhne GmbH');

        $build = fn (string $encoding): array => $this->unzip($this->service->build(
            $this->organization,
            Carbon::parse('2025-06-01'),
            Carbon::parse('2025-06-30'),
            ['rechnungen'],
            $this->accountant,
            $encoding,
        )['content']);

        // UTF-8: „ü" als 2-Byte-Sequenz; index.xml deklariert UTF8.
        $utf8 = $build(GdpduExportService::ENCODING_UTF8);
        $this->assertStringContainsString('<Encoding>UTF8</Encoding>', $utf8['index.xml']);
        $this->assertStringContainsString("M\xC3\xBCller", $utf8['rechnungen.csv']);

        // CP1252 (ANSI): „ü" als Einzelbyte 0xFC; index.xml deklariert ANSI.
        $cp = $build(GdpduExportService::ENCODING_CP1252);
        $this->assertStringContainsString('<Encoding>ANSI</Encoding>', $cp['index.xml']);
        $this->assertStringContainsString("M\xFCller", $cp['rechnungen.csv']);
        $this->assertStringNotContainsString("M\xC3\xBCller", $cp['rechnungen.csv']);

        // ISO-8859-15: eigene Deklaration (Variante); „ü" ebenfalls 0xFC.
        $iso = $build(GdpduExportService::ENCODING_ISO_8859_15);
        $this->assertStringContainsString('<Encoding>ISO-8859-15</Encoding>', $iso['index.xml']);
        $this->assertStringContainsString("M\xFCller", $iso['rechnungen.csv']);
    }

    public function test_time_entries_section_exports_worked_time(): void {
        $this->timeEntry($this->organization, 'Meier Bau GmbH', 'Dachsanierung', minutes: 90, description: 'Vor-Ort-Beratung');

        // Fremd-Organisation muss außen vor bleiben (Mandantengrenze).
        $otherOrg = Organization::factory()->create();
        $this->timeEntry($otherOrg, 'Fremd AG', 'Fremdprojekt', personnelNumber: 'X-999');

        $result = $this->service->build(
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['time_entries'],
            $this->accountant,
        );

        $files = $this->unzip($result['content']);
        $this->assertArrayHasKey('zeitnachweise.csv', $files);
        $csv = $files['zeitnachweise.csv'];
        $this->assertStringContainsString('P-4711', $csv);
        $this->assertStringContainsString('Meier Bau GmbH', $csv);
        $this->assertStringContainsString('Dachsanierung', $csv);
        $this->assertStringContainsString('Vor-Ort-Beratung', $csv);
        $this->assertStringContainsString('1,50', $csv); // 90 Min = 1,50 Std, Dezimalkomma
        $this->assertStringContainsString('Ja', $csv);   // abrechenbar

        // Mandantengrenze: keine fremden Zeilen.
        $this->assertStringNotContainsString('Fremdprojekt', $csv);
        $this->assertStringNotContainsString('X-999', $csv);

        // index.xml deklariert die Tabelle als Zeitnachweise mit korrektem Numeric-Feld.
        $this->assertStringContainsString('Zeitnachweise', $files['index.xml']);
        $this->assertStringContainsString('Dauer_Stunden', $files['index.xml']);
        $this->assertStringContainsString('zeitnachweise.csv', $files['index.xml']);
    }

    public function test_permitted_user_downloads_zip(): void {
        $this->invoice($this->organization, 'RE-2025-009');

        $this->actingAs($this->accountant)
            ->post(route('finance.gobd.export'), ['from' => '2025-01-01', 'to' => '2025-12-31', 'sections' => ['invoices']])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }
}
