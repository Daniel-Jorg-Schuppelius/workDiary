<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\DatevBatchStatus;
use App\Enums\User\Permission;
use App\Models\{Customer, Invoice, User};
use App\Models\Finance\DatevBookingBatch;
use App\Services\Finance\Datev\{DatevBookingAdapter, DatevBookingConfig};
use App\Services\Finance\{DatevBookingException, DatevBookingService, FinancialFormatsSupport};
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Gate, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3): Sammeln,
 * Finalisieren, CSV-Inhalt, Doppel-Übergabe-Schutz, Unveränderlichkeit,
 * Download-Gate, Berechtigungen und Mandantengrenze.
 */
class DatevBookingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization([
            'settings' => ['datev' => ['advisor_number' => 12345, 'client_number' => 1, 'skr' => 'skr03', 'debtor_base' => 10000]],
        ]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme GmbH']);
    }

    private function config(): DatevBookingConfig {
        return DatevBookingConfig::forOrganization($this->organization);
    }

    private function service(): DatevBookingService {
        return app(DatevBookingService::class);
    }

    private function makeInvoice(string $number, string $status = Invoice::STATUS_ISSUED, ?Customer $customer = null): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'number' => $number,
            'status' => $status,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);
    }

    private function period(): array {
        return ['from' => '2026-05-01', 'to' => '2026-05-31'];
    }

    public function test_collect_booking_ready_includes_issued_invoices_in_period(): void {
        $this->makeInvoice('RE-1');
        $this->makeInvoice('RE-2', Invoice::STATUS_DRAFT);          // Entwurf ⇒ ausgeschlossen
        $this->makeInvoice('RE-3', Invoice::STATUS_CANCELLED);      // storniert ⇒ ausgeschlossen

        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $this->assertCount(1, $sources);
        $this->assertSame('RE-1', $sources->first()->number);
    }

    public function test_collect_excludes_invoices_of_externally_led_customers(): void {
        $external = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => 'lexoffice',
        ]);
        $this->makeInvoice('RE-EXT', Invoice::STATUS_ISSUED, $external);
        $this->makeInvoice('RE-LOCAL');

        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $this->assertSame(['RE-LOCAL'], $sources->pluck('number')->all());
    }

    public function test_collect_excludes_invoices_already_in_exported_batch(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $invoice = $this->makeInvoice('RE-ONCE');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $this->service()->finalize($batch, $this->config(), $this->admin);

        // Zweiter Lauf: dieselbe Rechnung darf nicht erneut erscheinen.
        $second = $this->service()->collectBookingReady($this->organization, $this->period());
        $this->assertCount(0, $second);
        $this->assertSame($invoice->id, $batch->sources->first()->source_id);
    }

    public function test_finalize_produces_csv_with_hash_and_marks_sources(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $this->makeInvoice('RE-FIN');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);

        $batch = $this->service()->finalize($batch, $this->config(), $this->admin);

        $this->assertSame(DatevBatchStatus::Exported, $batch->status);
        $this->assertNotNull($batch->file_path);
        $this->assertSame(64, strlen((string) $batch->file_hash));

        $disk = Storage::disk(DatevBookingService::DISK);
        $this->assertTrue($disk->exists((string) $batch->file_path));

        $csv = (string) $disk->get((string) $batch->file_path);
        $this->assertSame($batch->file_hash, hash('sha256', $csv));
        // Stichprobe: DATEV-Header-Kennzeichen, Belegnummer und Bruttobetrag.
        $this->assertStringContainsString('Buchungsstapel', $csv);
        $this->assertStringContainsString('RE-FIN', $csv);
        $this->assertStringContainsString('119,00', $csv);
    }

    public function test_finalize_records_hash_chain_event(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $this->makeInvoice('RE-EVT');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $this->service()->finalize($batch, $this->config(), $this->admin);

        $events = $batch->events()->orderBy('id')->pluck('event')->all();
        $this->assertSame(['created', 'finalized'], $events);
        $this->assertNotNull($batch->events()->where('event', 'finalized')->first()->hash);
    }

    public function test_finalize_records_format_version_and_roundtrip(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $this->makeInvoice('RE-RT');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $this->service()->finalize($batch, $this->config(), $this->admin);

        $payload = $batch->events()->where('event', 'finalized')->first()->payload;
        $this->assertSame(700, (int) data_get($payload, 'format_version'));
        $this->assertTrue((bool) data_get($payload, 'roundtrip_ok'));
        $this->assertNotNull(data_get($payload, 'format_type'));
    }

    public function test_roundtrip_validation_detects_row_count_mismatch(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $batch = DatevBookingBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'period_from' => '2026-05-01',
            'period_to' => '2026-05-31',
            'booking_count' => 1,
        ]);
        $adapter = new DatevBookingAdapter();
        $rows = [[
            'amount' => 119.0,
            'soll_haben' => 'S',
            'account' => '10001',
            'contra_account' => '8400',
            'tax_key' => '3',
            'date' => new DateTimeImmutable('2026-05-15'),
            'document_ref' => 'RE-RT',
            'text' => 'Test',
        ]];
        $csv = $adapter->generate($batch, $this->config(), $rows);

        $ok = $adapter->validateRoundtrip($csv, $this->config(), 1);
        $this->assertTrue($ok['ok'], implode('; ', $ok['errors']));
        $this->assertSame(700, $ok['version']);
        $this->assertSame(1, $ok['rows']);

        // Falsche Erwartung ⇒ Roundtrip schlägt fehl (und würde finalize abbrechen).
        $bad = $adapter->validateRoundtrip($csv, $this->config(), 2);
        $this->assertFalse($bad['ok']);
        $this->assertNotEmpty($bad['errors']);
    }

    /**
     * Kanzlei-Felder (Feature 135, MVP-700): Fälligkeit, Skonto + Skontotyp
     * und KOST1 landen an ihrem EXTF-Spaltenindex; Beleglink bleibt leer.
     */
    public function test_finalize_writes_due_date_discount_and_cost_center(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        \App\Models\CostCenterRule::query()->create([
            'organization_id' => $this->organization->id,
            'cost_center' => 'KST100',
            'priority' => 0,
        ]);
        $invoice = $this->makeInvoice('RE-KOST');
        $invoice->update(['due_on' => '2026-06-14', 'skonto_percent' => '2.00', 'skonto_days' => 10]);

        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $batch = $this->service()->finalize($batch, $this->config(), $this->admin);

        $csv = (string) Storage::disk(DatevBookingService::DISK)->get((string) $batch->file_path);
        $fields = explode(';', explode("\n", $csv)[2]);

        $this->assertSame('2,38', $fields[12], 'Skonto (Feld 13)');
        $this->assertSame('', $fields[19], 'Beleglink (Feld 20) bleibt leer — kein DATEV-Beleg-GUID');
        $this->assertSame('KST100', $fields[36], 'KOST1 (Feld 37)');
        $this->assertSame('14062026', $fields[92], 'Zugeordnete Fälligkeit (Feld 93)');
        $this->assertSame('2', $fields[93], 'Skontotyp (Feld 94) = Verkauf');
    }

    /** Ohne Konditionen und Regeln bleibt der Stapel wie bisher — leere Felder. */
    public function test_finalize_leaves_optional_fields_empty_without_conditions(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $this->makeInvoice('RE-PLAIN');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $batch = $this->service()->finalize($batch, $this->config(), $this->admin);

        $fields = explode(';', explode("\n", (string) Storage::disk(DatevBookingService::DISK)->get((string) $batch->file_path))[2]);

        $this->assertSame('', $fields[12]);
        $this->assertSame('', $fields[36]);
        $this->assertSame('', $fields[92]);
        $this->assertSame('', $fields[93]);
    }

    public function test_finalized_batch_is_immutable(): void {
        $batch = DatevBookingBatch::factory()->exported()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $batch->update(['total_amount' => '999.00']);
    }

    public function test_finalize_twice_throws(): void {
        $batch = DatevBookingBatch::factory()->exported()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->expectException(DatevBookingException::class);
        $this->service()->finalize($batch, $this->config(), $this->admin);
    }

    public function test_download_requires_permission_and_streams_file(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }

        $this->makeInvoice('RE-DL');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $this->service()->finalize($batch, $this->config(), $this->admin);

        $this->actingAs($this->admin)
            ->get(route('finance.datev.download', $batch))
            ->assertOk()
            ->assertDownload();
    }

    public function test_buchhaltung_can_export_but_not_configure(): void {
        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);

        // Spatie-Team-Kontext auf die Organisation setzen (im echten Request
        // erledigt das die Middleware) — etabliertes Test-Muster, siehe
        // RoleProfilesTest/SoftwareControllerTest.
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->assertTrue($accountant->can(Permission::FinanceBookingExport->value));
        $this->assertFalse($accountant->can(Permission::FinanceConfig->value));
        $this->assertTrue(Gate::forUser($accountant)->allows('viewAny', DatevBookingBatch::class));
        $this->assertTrue(Gate::forUser($accountant)->allows('create', DatevBookingBatch::class));
    }

    public function test_user_without_permission_is_denied_index(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('finance.datev.index'))
            ->assertForbidden();
    }

    public function test_batch_is_scoped_to_organization(): void {
        $other = \App\Models\Organization::factory()->create();
        $foreignBatch = DatevBookingBatch::factory()->create(['organization_id' => $other->id]);

        // Unter der currentOrganization darf der fremde Stapel nicht sichtbar sein.
        $this->assertNull(DatevBookingBatch::find($foreignBatch->id));
    }
}
