<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingCreditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, SelfBillingStatus};
use App\Models\Invoicing\{IncomingEInvoice, SelfBillingCredit};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Gutschriftsverfahren als eigener Belegtyp (MVP-1280). */
final class SelfBillingCreditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->admin = $this->orgAdmin();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function supplier(array $attributes = []): Supplier {
        return Supplier::factory()->create([
            'organization_id' => $this->organization->id, 'name' => 'Montage Huber', 'company' => 'Montage Huber GmbH',
            'vat_id' => 'DE123456789', 'address_street' => 'Werkstraße 4', 'address_zip' => '80331', 'address_city' => 'München', 'country' => 'DE',
            'self_billing_agreed_on' => '2026-01-15', ...$attributes,
        ]);
    }

    private function draftFor(Supplier $supplier): SelfBillingCredit {
        $this->actingAs($this->admin)->post(route('invoices.self-billing.store'), [
            'supplier_id' => $supplier->sqid, 'service_from' => '2026-09-01', 'service_until' => '2026-09-30',
        ])->assertSessionHasNoErrors();
        $credit = SelfBillingCredit::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('invoices.self-billing.lines.store', $credit), [
            'description' => 'Montagestunden September', 'quantity' => '20', 'unit' => 'Std.', 'unit_price' => '50', 'tax_rate' => '19',
        ])->assertSessionHasNoErrors();

        return $credit->refresh();
    }

    public function test_issuing_numbers_freezes_totals_and_hands_the_389_invoice_to_the_inbox(): void {
        $supplier = $this->supplier();
        $credit = $this->draftFor($supplier);
        $this->actingAs($this->admin)->get(route('invoices.self-billing.show', $credit))->assertOk()->assertSee(__('invoicing.self_billing.action.issue'));

        $this->actingAs($this->admin)->post(route('invoices.self-billing.issue', $credit))->assertSessionHasNoErrors();

        $credit->refresh();
        $this->assertSame(SelfBillingStatus::Issued, $credit->status);
        $this->assertSame('GS-2026-0001', $credit->number);
        $this->assertSame('1000.00', $credit->net_amount?->getAmount());
        $this->assertSame('1190.00', $credit->gross_amount?->getAmount());

        $xml = (string) $this->actingAs($this->admin)->get(route('invoices.self-billing.xml', $credit))->assertOk()->getContent();
        $this->assertStringContainsString('>389<', $xml);
        $this->assertStringContainsString('Montage Huber GmbH', $xml);

        $incoming = IncomingEInvoice::query()->findOrFail($credit->incoming_e_invoice_id);
        $this->assertSame((int) $supplier->id, (int) $incoming->supplier_id);
        $this->assertSame('self_billing', $incoming->source);

        // Ausgestellt ist festgeschrieben.
        $this->actingAs($this->admin)->post(route('invoices.self-billing.lines.store', $credit), [
            'description' => 'Nachtrag', 'quantity' => '1', 'unit_price' => '10',
        ])->assertSessionHasErrors('self_billing');
        $this->expectException(RuntimeException::class);
        $credit->update(['note' => 'geändert']);
    }

    public function test_without_agreement_or_tax_id_the_credit_cannot_be_issued(): void {
        $supplier = $this->supplier(['self_billing_agreed_on' => null, 'vat_id' => null, 'tax_number' => null]);
        $credit = SelfBillingCredit::query()->create(['organization_id' => $this->organization->id, 'supplier_id' => $supplier->id, 'service_from' => '2026-09-01', 'service_until' => '2026-09-30']);

        $this->actingAs($this->admin)->get(route('invoices.self-billing.show', $credit))
            ->assertOk()
            ->assertSee(__('invoicing.self_billing.errors.no_agreement'))
            ->assertSee(__('invoicing.self_billing.errors.no_tax_id'))
            ->assertDontSee(route('invoices.self-billing.issue', $credit), false);
        $this->actingAs($this->admin)->post(route('invoices.self-billing.issue', $credit))->assertSessionHasErrors('self_billing');
        $this->assertSame(SelfBillingStatus::Draft, $credit->fresh()->status);
        // Ohne Vereinbarung steht der Lieferant nicht zur Auswahl.
        $this->actingAs($this->admin)->get(route('invoices.self-billing.create'))->assertDontSee('value="' . $supplier->sqid . '"', false);
    }

    public function test_cancel_rejects_the_inbox_record_and_pdf_names_the_document(): void {
        $credit = $this->draftFor($this->supplier());
        $this->actingAs($this->admin)->post(route('invoices.self-billing.issue', $credit));
        $credit->refresh();

        $this->mock(DocumentDesignRenderer::class, function ($mock): void {
            $mock->shouldReceive('renderPdf')->once()->withArgs(fn (RenderDocumentKind $kind, string $view, array $data): bool => $kind === RenderDocumentKind::CreditNote
                && str_contains(view($view, $data)->render(), __('invoicing.self_billing.document_title')))->andReturn('%PDF-1.4');
        });
        $this->actingAs($this->admin)->get(route('invoices.self-billing.pdf', $credit))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->admin)->post(route('invoices.self-billing.cancel', $credit), ['reason' => 'Falscher Zeitraum'])->assertSessionHasNoErrors();
        $this->assertSame(SelfBillingStatus::Cancelled, $credit->fresh()->status);
        $this->assertSame(IncomingEInvoiceStatus::Rejected, IncomingEInvoice::query()->findOrFail($credit->incoming_e_invoice_id)->status);
        $this->actingAs($this->admin)->get(route('invoices.self-billing.index'))->assertOk()->assertSee('GS-2026-0001');
    }
}
