<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceTransferStatus};
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\{Organization, PluginError, User};
use App\Models\Supplier\Supplier;
use App\Modules\ModuleRegistry;
use App\Services\Invoicing\Contracts\IncomingInvoiceTransferTarget;
use App\Services\Invoicing\Dto\IncomingInvoiceTransferResult;
use App\Services\Invoicing\EInvoice\{IncomingInvoiceMatcher, IncomingInvoiceTransferService};
use App\Services\Stammdaten\CollectiveContacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Feature 163, MVP-1111: Übergabe an die Buchhaltungsziele (Kern mit Fake-Ziel). */
final class IncomingInvoiceTransferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private FakeTransferTarget $target;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->target = new FakeTransferTarget;
        $this->app->instance(FakeTransferTarget::class, $this->target);
        $this->app->make(ModuleRegistry::class)->contribute(IncomingInvoiceTransferTarget::class, FakeTransferTarget::class);
    }

    public function test_assignment_hands_over_once_and_records_the_handover(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Holz AG']);
        $incoming = $this->row();

        app(IncomingInvoiceMatcher::class)->assign($incoming, $supplier, IncomingInvoiceMatchKind::Manual, $this->admin);

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->status);
        $this->assertSame('EXT-1', $journal->external_id);
        $this->assertSame(1, $journal->attempts);
        $this->assertNotNull($incoming->refresh()->transferred_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.transferred', 'auditable_id' => $incoming->id]);

        app(IncomingInvoiceTransferService::class)->transfer($incoming, $this->admin);
        app(IncomingInvoiceTransferService::class)->retryOpen($this->organization);
        $this->assertSame(1, $this->target->calls);
    }

    public function test_the_gate_holds_back_and_names_the_reasons(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $incoming = $this->row(['supplier_id' => $supplier->id, 'summary' => ['flags' => ['not_addressed', 'totals_mismatch']]]);

        [$journal] = app(IncomingInvoiceTransferService::class)->transfer($incoming);

        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertStringContainsString((string) __('Nicht an uns adressiert.'), (string) $journal->error);
        $this->assertStringContainsString((string) __('Summen widersprüchlich.'), (string) $journal->error);
        $this->assertSame(0, $this->target->calls);
        $this->assertNull($incoming->refresh()->transferred_at);
    }

    public function test_a_collective_supplier_waits_for_reverse_charge(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        $incoming = $this->row(['supplier_id' => $collective->id, 'summary' => ['tax_breakdown' => [['category' => 'AE', 'percent' => 0, 'net' => 100, 'tax' => 0]]]]);

        [$journal] = app(IncomingInvoiceTransferService::class)->transfer($incoming);

        $this->assertSame(IncomingInvoiceTransferStatus::Waiting, $journal->status);
        $this->assertSame(0, $this->target->calls);
    }

    public function test_failures_retry_five_times_then_reach_the_plugin_inbox_and_the_button_still_works(): void {
        $this->target->failWith = new RuntimeException('Zielsystem nicht erreichbar');
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $incoming = $this->row(['supplier_id' => $supplier->id]);
        $service = app(IncomingInvoiceTransferService::class);

        for ($i = 0; $i < 7; $i++) {
            $service->retryOpen($this->organization);
        }

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(IncomingInvoiceTransferStatus::Failed, $journal->status);
        $this->assertSame(IncomingInvoiceTransferService::MAX_ATTEMPTS, $journal->attempts);
        $this->assertSame(1, PluginError::query()->where('plugin_id', FakeTransferTarget::KEY)->count());

        $this->target->failWith = null;
        $this->post(route('finance.incoming-invoices.transfer', $incoming))
            ->assertRedirect(route('finance.incoming-invoices.show', $incoming->document))
            ->assertSessionHas('success');
        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->refresh()->status);
    }

    public function test_waiting_handovers_keep_the_note_and_show_up_in_the_tabs(): void {
        $this->target->result = IncomingInvoiceTransferResult::waiting('Rolle in Lexware ergänzen');
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $waiting = $this->row(['supplier_id' => $supplier->id, 'invoice_number' => 'RE-WARTET']);
        app(IncomingInvoiceTransferService::class)->transfer($waiting);
        $this->target->result = null;
        $this->target->failWith = new RuntimeException('kaputt');
        $failed = $this->row(['supplier_id' => $supplier->id, 'invoice_number' => 'RE-KAPUTT']);
        app(IncomingInvoiceTransferService::class)->transfer($failed);

        $this->get(route('finance.incoming-invoices.index', ['tab' => 'transfer']))
            ->assertOk()->assertSee('RE-WARTET')->assertDontSee('RE-KAPUTT');
        $this->get(route('finance.incoming-invoices.index', ['tab' => 'failed']))
            ->assertOk()->assertSee('RE-KAPUTT')->assertDontSee('RE-WARTET');
        $this->get(route('finance.incoming-invoices.show', $waiting->document))
            ->assertOk()->assertSee('Rolle in Lexware ergänzen')->assertSee(FakeTransferTarget::LABEL);
    }

    public function test_the_hourly_command_retries_open_handovers_and_leaves_finished_ones(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->target->failWith = new RuntimeException('kurz weg');
        $open = $this->row(['supplier_id' => $supplier->id]);
        app(IncomingInvoiceTransferService::class)->transfer($open);
        $this->row();
        $this->target->failWith = null;

        $this->artisan('incoming-invoices:transfer')->expectsOutputToContain('1 übergeben, 0 fehlgeschlagen')->assertSuccessful();

        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, IncomingEInvoiceTransfer::query()->sole()->status);
        $calls = $this->target->calls;
        $this->artisan('incoming-invoices:transfer')->doesntExpectOutputToContain('übergeben')->assertSuccessful();
        $this->assertSame($calls, $this->target->calls);
    }

    public function test_without_an_enabled_target_the_button_keeps_the_approval_rule(): void {
        $this->target->enabled = false;
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $incoming = $this->row(['supplier_id' => $supplier->id]);

        $this->post(route('finance.incoming-invoices.transfer', $incoming))->assertSessionHas('error');
        $this->assertNull($incoming->refresh()->transferred_at);
        $this->assertSame(0, IncomingEInvoiceTransfer::query()->count());
    }

    /** @param  array<string, mixed>  $attributes */
    private function row(array $attributes = []): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Holz AG', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
            ...$attributes,
        ]);
    }
}

final class FakeTransferTarget implements IncomingInvoiceTransferTarget {
    public const KEY = 'fake-ledger';

    public const LABEL = 'Testbuchhaltung';

    public bool $enabled = true;

    public int $calls = 0;

    public ?\Throwable $failWith = null;

    public ?IncomingInvoiceTransferResult $result = null;

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return self::LABEL;
    }

    public function isEnabled(Organization $organization): bool {
        return $this->enabled;
    }

    public function appliesTo(IncomingEInvoice $incoming): bool {
        return true;
    }

    public function transfer(IncomingEInvoice $incoming, IncomingEInvoiceTransfer $journal): IncomingInvoiceTransferResult {
        $this->calls++;
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return $this->result ?? IncomingInvoiceTransferResult::transferred('EXT-' . $this->calls, $incoming->invoice_number);
    }
}
