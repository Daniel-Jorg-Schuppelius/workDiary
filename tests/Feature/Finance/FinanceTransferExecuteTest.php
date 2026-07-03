<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceTransferExecuteTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, MaterialUsage, Project, TimeEntry, Timesheet, User};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\Targets\{FileTarget, LexofficeTarget};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Ziel-Adapter der Faktura-Übergabe (Feature 045, Teil B): Lexoffice-
 * Rechnungsentwurf über FakePluginHttp (PluginApiClient = php-api-toolkit) und
 * Datei-Übergabepaket (Storage::fake) inkl. Gate-geprüftem Download.
 */
class FinanceTransferExecuteTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    private BillingTransferService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'email' => 'billing@acme.test',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Lexoffice,
            'created_by' => $this->accountant->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->accountant->id,
        ]);

        $this->service = app(BillingTransferService::class);

        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');
    }

    private function makeTimeEntry(array $overrides = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ], $overrides));
    }

    private function makeMaterialUsage(): MaterialUsage {
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->accountant->id,
            'project_id' => $this->project->id,
            'work_date' => '2030-04-02',
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project,
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft,
        ]);

        return MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Kabel',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
            'billed' => false,
        ]);
    }

    private function confirmedTransfer(TransferTarget $target, TransferChannel $channel = TransferChannel::Time): BillingTransfer {
        $transfer = $this->service->createDraft(
            $this->customer,
            $channel,
            $target,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
        $this->service->confirm($transfer, $this->accountant);

        return $transfer->fresh();
    }

    // ── Lexoffice-Target ────────────────────────────────────────────────

    public function test_execute_lexoffice_creates_draft_invoice_and_marks_transferred(): void {
        $entry = $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::Lexoffice);

        $fake = FakePluginHttp::fake([
            // Contact-Lookup (kein bestehender Kontakt → Suche per E-Mail)
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response([
                'content' => [['id' => 'contact-uuid-1']],
            ], 200),
            // Invoice-Create (Entwurf) → 201
            'https://api.lexoffice.io/v1/invoices*' => FakePluginHttp::response([
                'id' => 'lex-invoice-1',
                'resourceUri' => 'https://api.lexoffice.io/v1/invoices/lex-invoice-1',
            ], 201),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer = $transfer->fresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertNotNull($transfer->transferred_at);

        // ExternalReference (invoice) zeigt auf den Übergabenachweis.
        $reference = ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficeTarget::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $transfer->getMorphClass())
            ->where('referenceable_id', $transfer->getKey())
            ->firstOrFail();
        $this->assertSame('lex-invoice-1', $reference->external_id);
        $this->assertSame('lex-invoice-1', data_get($reference->payload, 'lexoffice_id'));
        $this->assertSame((int) $reference->id, (int) $transfer->external_reference_id);

        // Quellen sind verbraucht.
        $this->assertTrue((bool) $entry->fresh()->exported);

        // Entwurf — kein finalize, korrekter Payload-Kern.
        $fake->assertSent(function (RequestInterface $request): bool {
            $url = (string) $request->getUri();
            if (! str_contains($url, '/invoices')) {
                return false;
            }
            $data = json_decode((string) $request->getBody(), true);

            // json_decode liefert für ganzzahlige Floats int → für den Vergleich casten.
            return ! str_contains($url, 'finalize')
                && $data['address']['contactId'] === 'contact-uuid-1'
                && $data['lineItems'][0]['type'] === 'custom'
                && (float) $data['lineItems'][0]['quantity'] === 2.0
                && $data['lineItems'][0]['unitName'] === 'h'
                && (float) $data['lineItems'][0]['unitPrice']['netAmount'] === 90.0;
        });
    }

    public function test_execute_lexoffice_uses_existing_contact_reference(): void {
        $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::Lexoffice);

        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->getKey(),
            'external_id' => 'contact-existing',
            'synced_at' => now(),
        ]);

        $fake = FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices*' => FakePluginHttp::response(['id' => 'lex-invoice-2'], 201),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
        $fake->assertSent(fn(RequestInterface $request) => str_contains((string) $request->getUri(), '/invoices')
            && json_decode((string) $request->getBody(), true)['address']['contactId'] === 'contact-existing');
        // Kein Contact-Lookup nötig.
        $fake->assertNotSent(fn(RequestInterface $request) => str_contains((string) $request->getUri(), '/contacts'));
    }

    public function test_execute_lexoffice_failure_marks_failed_and_keeps_sources(): void {
        $entry = $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::Lexoffice);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [['id' => 'contact-uuid-1']]], 200),
            'https://api.lexoffice.io/v1/invoices*' => FakePluginHttp::response(['message' => 'boom'], 500),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasErrors('transfer');

        $transfer = $transfer->fresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertNotNull($transfer->failure_reason);
        $this->assertStringContainsString('500', (string) $transfer->failure_reason);

        // Quellen bleiben unverbraucht (Retry möglich), keine ExternalReference.
        $this->assertFalse((bool) $entry->fresh()->exported);
        $this->assertSame(0, ExternalReference::query()
            ->where('external_type', LexofficeTarget::EXT_TYPE_INVOICE)->count());
    }

    public function test_execute_lexoffice_unconfigured_marks_failed_without_http(): void {
        // Kein API-Key (weder Org- noch globale Konfig) ⇒ Adapter bricht ab,
        // BEVOR ein HTTP-Call passiert; Quellen bleiben unverbraucht.
        config()->set('plugins.lexoffice.api_key', '');

        $entry = $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::Lexoffice);

        $fake = FakePluginHttp::fake();

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasErrors('transfer');

        $transfer = $transfer->fresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertNotNull($transfer->failure_reason);
        $this->assertFalse((bool) $entry->fresh()->exported);
        $fake->assertNothingSent();
    }

    public function test_execute_lexoffice_without_invoice_id_marks_failed(): void {
        // 201, aber ohne `id` im Body ⇒ Adapter wirft, Übergabe gilt als Fehler.
        $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::Lexoffice);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [['id' => 'c1']]], 200),
            'https://api.lexoffice.io/v1/invoices*' => FakePluginHttp::response([], 201),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasErrors('transfer');

        $this->assertSame(TransferStatus::Failed, $transfer->fresh()->status);
        $this->assertSame(0, ExternalReference::query()
            ->where('external_type', LexofficeTarget::EXT_TYPE_INVOICE)->count());
    }

    public function test_execute_from_draft_is_rejected_without_target_call(): void {
        $this->makeTimeEntry();
        $transfer = $this->service->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::Lexoffice,
            [],
            null,
            $this->accountant,
        );

        $fake = FakePluginHttp::fake();

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasErrors('status');

        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $fake->assertNothingSent();
    }

    // ── File-Target ─────────────────────────────────────────────────────

    public function test_execute_file_target_writes_time_csv(): void {
        Storage::fake(FileTarget::DISK);

        $entry = $this->makeTimeEntry(['description' => 'Serverwartung']);
        $transfer = $this->confirmedTransfer(TransferTarget::File);

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasNoErrors();

        $transfer = $transfer->fresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertNotNull($transfer->file_path);
        $this->assertStringStartsWith(FileTarget::BASE_PATH . '/', $transfer->file_path);
        $this->assertTrue((bool) $entry->fresh()->exported);

        $this->assertTrue(Storage::disk(FileTarget::DISK)->exists($transfer->file_path));
        $content = Storage::disk(FileTarget::DISK)->get($transfer->file_path);

        // Kopf: ehrliche Kennzeichnung + Hash, dann Header, Zeile, Summe.
        $this->assertStringContainsString((string) __('finance.csv.package_title'), $content);
        $this->assertStringContainsString($transfer->payload_hash, $content);
        $this->assertStringContainsString(
            __('finance.csv.date') . ';' . __('finance.csv.employee') . ';' . __('finance.csv.project'),
            $content,
        );
        $this->assertStringContainsString('2030-04-01', $content);
        $this->assertStringContainsString('Serverwartung', $content);
        $this->assertStringContainsString('2.00', $content);   // Stunden
        $this->assertStringContainsString('180.00', $content); // Summe Betrag (rate-Snapshot 2h × 90)
        $this->assertStringContainsString((string) __('finance.csv.total'), $content);
    }

    public function test_execute_file_target_writes_material_csv_for_datev_target(): void {
        Storage::fake(FileTarget::DISK);

        $this->customer->update(['billing_mode' => BillingMode::Datev]);
        $usage = $this->makeMaterialUsage();
        $transfer = $this->confirmedTransfer(TransferTarget::Datev, TransferChannel::Material);

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasNoErrors();

        $transfer = $transfer->fresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertTrue((bool) $usage->fresh()->billed);

        $content = Storage::disk(FileTarget::DISK)->get($transfer->file_path);
        $this->assertStringContainsString(
            __('finance.csv.product') . ';' . __('finance.csv.quantity') . ';' . __('finance.csv.unit'),
            $content,
        );
        $this->assertStringContainsString('Kabel', $content);
        $this->assertStringContainsString('3.00', $content);
        $this->assertStringContainsString('30.00', $content);
    }

    public function test_download_streams_package_and_requires_view_permission(): void {
        Storage::fake(FileTarget::DISK);

        $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::File);
        $this->post(route('finance.transfers.execute', $transfer))->assertSessionHasNoErrors();
        $transfer = $transfer->fresh();

        $this->get(route('finance.transfers.download', $transfer))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)
            ->get(route('finance.transfers.download', $transfer))
            ->assertForbidden();
    }

    public function test_download_is_unavailable_without_file(): void {
        $this->makeTimeEntry();
        $transfer = $this->confirmedTransfer(TransferTarget::Lexoffice);

        $this->get(route('finance.transfers.download', $transfer))->assertNotFound();
    }
}
