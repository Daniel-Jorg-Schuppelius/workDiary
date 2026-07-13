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

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\{AllocationKind, TransactionDirection};
use App\Enums\User\Permission;
use App\Models\{Customer, Expense, ExpenseCategory, GobdExport, Invoice, InvoiceItem, Organization, Project, TimeEntry, User};
use App\Models\Finance\{BankStatement, BankTransaction, DatevBookingBatch, PaymentAllocation};
use App\Services\Finance\GdpduExportService;
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

    /** Exportierter (festgeschriebener) Stapel samt Quellposten-Snapshots. */
    private function exportedBatch(Organization $org, int $batchNo, string $documentRef = 'RE-2025-100', bool $withReversal = true): DatevBookingBatch {
        $batch = DatevBookingBatch::factory()->exported()->create([
            'organization_id' => $org->id,
            'batch_no' => $batchNo,
            'period_from' => '2025-06-01',
            'period_to' => '2025-06-30',
            'selection_mode' => 'manual',
            'booking_count' => $withReversal ? 2 : 1,
            'total_amount' => '238.00',
            'finalized_at' => Carbon::parse('2025-07-01 10:00:00'),
            'file_hash' => hash('sha256', 'nachweis-' . $batchNo),
        ]);
        $batch->sources()->create([
            'source_type' => Invoice::class,
            'source_id' => 1,
            'debtor_account' => '10001',
            'revenue_account' => '8400',
            'soll_haben' => 'S',
            'amount' => '119.00',
            'tax_key' => '3',
            'document_ref' => $documentRef,
            'is_reversal' => false,
        ]);
        if ($withReversal) {
            $batch->sources()->create([
                'source_type' => Invoice::class,
                'source_id' => 2,
                'debtor_account' => '10001',
                'revenue_account' => '8400',
                'soll_haben' => 'S',
                'amount' => '119.00',
                'tax_key' => '3',
                'document_ref' => 'STORNO-' . $documentRef,
                'is_reversal' => true,
            ]);
        }

        return $batch;
    }

    private function bankTransaction(Organization $org, string $bookingDate, string $amount, TransactionDirection $direction = TransactionDirection::Credit, ?string $endToEndId = null, bool $isReversal = false): BankTransaction {
        return BankTransaction::factory()->create([
            'organization_id' => $org->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $org->id])->id,
            'booking_date' => $bookingDate,
            'amount' => $amount,
            'direction' => $direction,
            'end_to_end_id' => $endToEndId,
            'is_reversal' => $isReversal,
        ]);
    }

    private function expense(Organization $org, ExpenseStatus $status, string $description, string $date = '2025-06-05', ?ExpenseCategory $category = null, ?User $approver = null): Expense {
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        return Expense::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'expense_category_id' => ($category ?? ExpenseCategory::factory()->create(['organization_id' => $org->id]))->id,
            'date' => $date,
            'vendor' => 'Bahn AG',
            'description' => $description,
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
            'status' => $status->value,
            'decided_at' => $status === ExpenseStatus::Draft || $status === ExpenseStatus::Pending ? null : Carbon::parse('2025-06-06 08:00:00'),
            'decided_by' => $status === ExpenseStatus::Draft || $status === ExpenseStatus::Pending ? null : $approver?->id,
        ]);
    }

    public function test_booking_batch_sections_export_persisted_evidence_only(): void {
        $batch = $this->exportedBatch($this->organization, 4242);
        // Entwurfs-Stapel (nicht festgeschrieben) und Fremd-Stapel bleiben außen vor.
        DatevBookingBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'batch_no' => 7777,
            'period_from' => '2025-06-01',
            'period_to' => '2025-06-30',
        ]);
        $this->exportedBatch(Organization::factory()->create(), 9999, 'RE-FREMD-1');

        $result = $this->service->build(
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['booking_batches', 'booking_batch_items'],
            $this->accountant,
        );
        $files = $this->unzip($result['content']);

        // Stapel-Kopf: Nummer, Zeitraum, Export-Zeitpunkt, Nachweis-Hash, Teilauswahl (A15).
        $head = $files['buchungsstapel.csv'];
        $this->assertStringContainsString('4242', $head);
        $this->assertStringContainsString('2025-06-01', $head);
        $this->assertStringContainsString('2025-07-01 10:00:00', $head);
        $this->assertStringContainsString((string) $batch->file_hash, $head);
        $this->assertStringContainsString('manual', $head);
        $this->assertStringContainsString('238,00', $head);
        $this->assertStringNotContainsString('7777', $head);
        $this->assertStringNotContainsString('9999', $head);

        // Positionen: persistierter Buchungs-Snapshot inkl. GU-/Storno-Kennzeichen.
        $items = $files['buchungsstapelpositionen.csv'];
        $this->assertStringContainsString('RE-2025-100', $items);
        $this->assertStringContainsString('STORNO-RE-2025-100', $items);
        $this->assertStringContainsString('Invoice', $items);
        $this->assertStringContainsString('8400', $items);
        $this->assertStringContainsString('Ja', $items);   // Generalumkehr
        $this->assertStringContainsString('Nein', $items);
        $this->assertStringNotContainsString('RE-FREMD-1', $items);

        // index.xml deklariert beide Tabellen im Beschreibungsstandard.
        $this->assertStringContainsString('Buchungsstapel', $files['index.xml']);
        $this->assertStringContainsString('buchungsstapel.csv', $files['index.xml']);
        $this->assertStringContainsString('buchungsstapelpositionen.csv', $files['index.xml']);
        $this->assertStringContainsString('Generalumkehr', $files['index.xml']);

        // Revisionssicherer Nachweis schließt die neuen Sections ein.
        $this->assertDatabaseHas('gobd_exports', [
            'organization_id' => $this->organization->id,
            'package_sha256' => $result['package_sha256'],
            'record_count' => 3, // 1 Stapel-Kopf + 2 Positionen
        ]);
    }

    public function test_payment_allocations_section_exports_allocations_with_chargeback(): void {
        $invoice = $this->invoice($this->organization, 'RE-2025-777');

        $tx = $this->bankTransaction($this->organization, '2025-06-10', '119.00', endToEndId: 'E2E-4711');
        PaymentAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_transaction_id' => $tx->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '119.00',
            'kind' => AllocationKind::Payment,
            'confirmed_at' => Carbon::parse('2025-06-11 09:00:00'),
        ]);

        // Rückläufer-Kompensation (MVP-334): negativer Betrag + Grund im note-Feld.
        $returnTx = $this->bankTransaction($this->organization, '2025-06-20', '119.00', TransactionDirection::Debit, isReversal: true);
        PaymentAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_transaction_id' => $returnTx->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '-119.00',
            'kind' => AllocationKind::Chargeback,
            'note' => 'RET#1 Rueckgabe MD06',
            'confirmed_at' => Carbon::parse('2025-06-21 09:00:00'),
        ]);

        // Außerhalb des Zeitraums, aufgehoben (unmatch) und fremde Organisation: nicht enthalten.
        $lateTx = $this->bankTransaction($this->organization, '2026-02-10', '50.00');
        PaymentAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_transaction_id' => $lateTx->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '50.00',
            'note' => 'AUSSERHALB-ZEITRAUM',
        ]);
        $unmatchedTx = $this->bankTransaction($this->organization, '2025-06-15', '10.00');
        PaymentAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_transaction_id' => $unmatchedTx->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '10.00',
            'note' => 'GELOESTE-ZUORDNUNG',
        ])->delete();
        $foreignOrg = Organization::factory()->create();
        PaymentAllocation::factory()->create([
            'organization_id' => $foreignOrg->id,
            'bank_transaction_id' => $this->bankTransaction($foreignOrg, '2025-06-12', '77.00')->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '77.00',
            'note' => 'FREMD-ZUORDNUNG',
        ]);

        $result = $this->service->build(
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['payment_allocations'],
            $this->accountant,
        );
        $files = $this->unzip($result['content']);

        $csv = $files['zahlungszuordnungen.csv'];
        $this->assertStringContainsString('2025-06-10', $csv);
        $this->assertStringContainsString('E2E-4711', $csv);
        $this->assertStringContainsString('payment', $csv);
        $this->assertStringContainsString('RE-2025-777', $csv);   // Gegenseite offener Posten
        $this->assertStringContainsString('chargeback', $csv);
        $this->assertStringContainsString('-119,00', $csv);       // Kompensation negativ
        $this->assertStringContainsString('RET#1 Rueckgabe MD06', $csv);
        $this->assertStringNotContainsString('AUSSERHALB-ZEITRAUM', $csv);
        $this->assertStringNotContainsString('GELOESTE-ZUORDNUNG', $csv);
        $this->assertStringNotContainsString('FREMD-ZUORDNUNG', $csv);

        $this->assertStringContainsString('Zahlungszuordnungen', $files['index.xml']);
        $this->assertStringContainsString('zahlungszuordnungen.csv', $files['index.xml']);
    }

    public function test_expenses_section_exports_only_approved_expenses(): void {
        $approver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Reisekosten']);

        $approved = $this->expense($this->organization, ExpenseStatus::Approved, 'Fahrt Kundentermin', category: $category, approver: $approver);
        $reimbursed = $this->expense($this->organization, ExpenseStatus::Reimbursed, 'ERSTATTET-SPESE', approver: $approver);
        $reimbursed->forceFill(['reimbursed_at' => Carbon::parse('2025-06-30 12:00:00')])->save();

        // Entwurf, offen, abgelehnt und fremde Organisation: nicht enthalten.
        $this->expense($this->organization, ExpenseStatus::Draft, 'ENTWURF-SPESE');
        $this->expense($this->organization, ExpenseStatus::Pending, 'OFFENE-SPESE');
        $this->expense($this->organization, ExpenseStatus::Rejected, 'ABGELEHNTE-SPESE', approver: $approver);
        $this->expense(Organization::factory()->create(), ExpenseStatus::Approved, 'FREMD-SPESE');

        $result = $this->service->build(
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['expenses'],
            $this->accountant,
        );
        $files = $this->unzip($result['content']);

        $csv = $files['spesen.csv'];
        $this->assertStringContainsString('E-' . $approved->id, $csv); // Beleg-Nr. wie im DATEV-Export
        $this->assertStringContainsString('Reisekosten', $csv);
        $this->assertStringContainsString('Bahn AG', $csv);
        $this->assertStringContainsString('Fahrt Kundentermin', $csv);
        $this->assertStringContainsString('100,00', $csv);  // Netto
        $this->assertStringContainsString('19,00', $csv);   // USt-Satz/-Betrag
        $this->assertStringContainsString('119,00', $csv);  // Brutto
        $this->assertStringContainsString('2025-06-06 08:00:00', $csv);          // Freigabe-Zeitpunkt
        $this->assertStringContainsString((string) $approver->id, $csv);         // Freigabe-Person als ID
        $this->assertStringContainsString('2025-06-30 12:00:00', $csv);          // Zahlungsstatus (Erstattung)
        $this->assertStringNotContainsString('ENTWURF-SPESE', $csv);
        $this->assertStringNotContainsString('OFFENE-SPESE', $csv);
        $this->assertStringNotContainsString('ABGELEHNTE-SPESE', $csv);
        $this->assertStringNotContainsString('FREMD-SPESE', $csv);

        $this->assertStringContainsString('Spesen', $files['index.xml']);
        $this->assertStringContainsString('spesen.csv', $files['index.xml']);
    }

    public function test_package_hash_is_reproducible_for_new_sections(): void {
        $this->exportedBatch($this->organization, 4242);
        $invoice = $this->invoice($this->organization, 'RE-2025-888');
        PaymentAllocation::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_transaction_id' => $this->bankTransaction($this->organization, '2025-06-10', '119.00')->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '119.00',
            'confirmed_at' => Carbon::parse('2025-06-11 09:00:00'),
        ]);
        $this->expense($this->organization, ExpenseStatus::Approved, 'Fahrt Kundentermin');

        $args = [
            $this->organization,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            ['booking_batches', 'booking_batch_items', 'payment_allocations', 'expenses'],
            $this->accountant,
        ];

        $a = $this->service->build(...$args);
        $b = $this->service->build(...$args);

        $this->assertSame($a['package_sha256'], $b['package_sha256']);
    }

    public function test_preflight_warns_about_draft_booking_batches(): void {
        DatevBookingBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'batch_no' => 7777,
            'period_from' => '2025-06-01',
            'period_to' => '2025-06-30',
        ]);

        $preflight = $this->service->preflight($this->organization, Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31'));

        $this->assertContains((string) __('gobd.preflight.draft_batches', ['count' => 1]), $preflight['warnings']);
    }
}
