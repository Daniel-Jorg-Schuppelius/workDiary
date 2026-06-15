<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * E-Rechnung (Feature 045, Abschnitt 8): Download-Route, Preflight-Redirect,
 * Hoheits-Sperre (BillingModeResolver), Gate und Cross-Org-Isolation.
 */
class XRechnungTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'WorkDiary Org']);
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings()]]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function sellerSettings(): array {
        return [
            'seller_name' => 'WorkDiary GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@workdiary.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'WorkDiary GmbH',
            'payment_terms_days' => 14,
        ];
    }

    private function makeInvoice(string $status = Invoice::STATUS_ISSUED, ?Customer $customer = null, ?int $orgId = null): Invoice {
        $orgId ??= $this->organization->id;
        $customer ??= $this->customer;

        $invoice = Invoice::create([
            'organization_id' => $orgId,
            'customer_id' => $customer->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-06-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'organization_id' => $orgId,
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);

        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer']);
    }

    public function test_download_returns_xml_for_complete_issued_invoice(): void {
        $invoice = $this->makeInvoice();

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('XRechnung_' . $invoice->number . '.xml', (string) $response->headers->get('Content-Disposition'));
        // Korrekte XRechnung-3.0-Kennung (xeinkauf.de) seit Toolkit v0.1.12.
        $this->assertStringContainsString(\ERechnungToolkit\Enums\ERechnungProfile::XRECHNUNG->value, $response->getContent());
        $this->assertStringContainsString('<cbc:BuyerReference>991-12345-67</cbc:BuyerReference>', $response->getContent());
    }

    public function test_download_redirects_with_error_when_buyer_reference_missing(): void {
        $this->customer->update(['buyer_reference' => null]);
        $invoice = $this->makeInvoice();

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            (string) __('invoicing.einvoice.error.missing_buyer_reference'),
            (string) session('error'),
        );
    }

    public function test_download_is_not_found_for_externally_billed_customer(): void {
        $invoice = $this->makeInvoice();
        $this->customer->update(['billing_mode' => 'lexoffice']);

        $this->actingAs($this->admin)
            ->get(route('invoices.einvoice', $invoice))
            ->assertNotFound();
    }

    public function test_download_redirects_for_draft_invoice(): void {
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error');
    }

    public function test_button_visible_on_issued_invoice_for_workdiary_mode(): void {
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(route('invoices.einvoice', $invoice), false);
    }

    public function test_button_hidden_for_draft_invoice(): void {
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('invoices.einvoice', $invoice), false);
    }

    public function test_button_hidden_for_externally_billed_customer(): void {
        $invoice = $this->makeInvoice();
        $this->customer->update(['billing_mode' => 'lexoffice']);

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('invoices.einvoice', $invoice), false);
    }

    public function test_non_billing_user_is_forbidden(): void {
        $invoice = $this->makeInvoice();
        $regular = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($regular)
            ->get(route('invoices.einvoice', $invoice))
            ->assertForbidden();
    }

    public function test_cross_org_invoice_is_not_accessible(): void {
        $invoice = $this->makeInvoice();

        // Admin einer fremden Organisation darf die Rechnung nicht erreichen.
        $orgB = Organization::factory()->create();
        $adminB = User::factory()->admin()->create(['organization_id' => $orgB->id]);
        app()->instance('currentOrganization', $orgB);

        $response = $this->actingAs($adminB)->get(route('invoices.einvoice', $invoice));
        $this->assertContains($response->status(), [403, 404], 'Cross-Org-Zugriff muss blockiert sein');
    }
}
