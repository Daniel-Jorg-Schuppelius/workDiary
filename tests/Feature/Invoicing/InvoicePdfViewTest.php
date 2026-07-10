<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePdfViewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whitebox 2026-07-10 (J3/G5): Die Rechnungs-Druckansicht zieht die
 * Rechtsangaben aus der Organisation DER RECHNUNG (auch ohne Auth-Kontext,
 * z. B. im Queue-Worker der Mail) und weist bei gemischten Positions-
 * Steuersätzen jeden Satz einzeln aus (§ 14 Abs. 4 UStG).
 */
class InvoicePdfViewTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create([
            'settings' => [
                'branding' => [
                    'legal' => [
                        'iban' => 'DE02120300000000202051',
                        'bank_name' => 'Testbank',
                        'account_holder' => 'ACME GmbH',
                    ],
                ],
            ],
        ]);
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function mixedRateInvoice(): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0001',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Fachbuch',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '7.00',
            'position' => 2,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    public function test_view_data_resolves_legal_from_invoice_org_without_auth(): void {
        $invoice = $this->mixedRateInvoice();

        // Kein Auth-User, kein Container-Binding — wie im Queue-Worker.
        app()->forgetInstance('currentOrganization');
        auth()->logout();

        $data = app(InvoicePdfRenderer::class)->viewData($invoice);

        $this->assertSame('DE02120300000000202051', $data['orgLegal']['iban'] ?? null, 'Rechtsangaben müssen aus der Org der Rechnung kommen, nicht aus dem Auth-Kontext.');
    }

    public function test_pdf_view_renders_tax_breakdown_per_rate(): void {
        $invoice = $this->mixedRateInvoice();

        $this->assertSame('26.00', $invoice->tax_amount);

        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice))->render();

        $this->assertStringContainsString('19% (100,00 EUR)', $html);
        $this->assertStringContainsString('7% (100,00 EUR)', $html);
        $this->assertStringContainsString('19,00 EUR', $html); // 19-%-Anteil
        $this->assertStringContainsString('7,00 EUR', $html);  // 7-%-Anteil
    }
}
