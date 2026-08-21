<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GirocodeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\{GirocodeService, InvoicePdfRenderer};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Girocode/EPC-QR auf dem Rechnungs-PDF (Feature 111, MVP-600).
 *
 * Schwerpunkt der Prüfung sind die Fälle, in denen der Code NICHT erscheinen
 * darf: Ein QR-Code, der auf ein unvollständiges Konto oder einen falschen
 * Betrag führt, sieht verbindlich aus und wird nicht nachgeprüft.
 */
class GirocodeTest extends TestCase {
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
                        'bic' => 'BYLADEM1001',
                        'bank_name' => 'Testbank',
                        'account_holder' => 'ACME GmbH',
                    ],
                ],
            ],
        ]);
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        Setting::set('invoicing.girocode_enabled', true, SettingScope::Organization, $this->org);
    }

    private function invoice(array $attributes = []): Invoice {
        $invoice = Invoice::create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0007',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ], $attributes));
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    private function legal(): array {
        return app(\App\Services\BrandingService::class)->legalFor($this->org);
    }

    public function test_payload_carries_iban_amount_and_invoice_number(): void {
        $payload = app(GirocodeService::class)->payload($this->invoice(), $this->legal());

        $this->assertNotNull($payload);
        $lines = explode("\n", $payload);
        $this->assertSame('BCD', $lines[0]);
        $this->assertSame('BYLADEM1001', $lines[4]);
        $this->assertSame('ACME GmbH', $lines[5]);
        $this->assertSame('DE02120300000000202051', $lines[6]);
        $this->assertSame('EUR119.00', $lines[7]);
        // Verwendungszweck ist die Rechnungsnummer, nicht die interne ID.
        $this->assertContains('R2030-0007', $lines);
    }

    public function test_disabled_by_default(): void {
        Setting::reset('invoicing.girocode_enabled', SettingScope::Organization, $this->org);

        $this->assertNull(app(GirocodeService::class)->payload($this->invoice(), $this->legal()));
    }

    public function test_no_code_without_iban(): void {
        $this->org->forceFill(['settings' => ['branding' => ['legal' => ['account_holder' => 'ACME GmbH']]]])->save();

        $this->assertNull(app(GirocodeService::class)->payload($this->invoice(), $this->legal()));
    }

    public function test_no_code_for_invalid_iban(): void {
        $this->org->forceFill(['settings' => ['branding' => ['legal' => [
            'iban' => 'DE00000000000000000000',
            'account_holder' => 'ACME GmbH',
        ]]]])->save();

        $this->assertNull(app(GirocodeService::class)->payload($this->invoice(), $this->legal()));
    }

    public function test_no_code_for_foreign_currency(): void {
        // Währung erst nach der Berechnung setzen: der Totals-Rechner besteht
        // zu Recht auf gleicher Währung von Beleg und Positionen.
        $invoice = $this->invoice();
        $invoice->forceFill(['currency' => 'CHF'])->save();

        $this->assertNull(app(GirocodeService::class)->payload($invoice->fresh(), $this->legal()));
    }

    public function test_no_code_for_paid_or_partially_paid_invoices(): void {
        $service = app(GirocodeService::class);

        $this->assertNull($service->payload($this->invoice(['status' => Invoice::STATUS_PAID]), $this->legal()));
        $this->assertNull($service->payload($this->invoice(['number' => 'R2030-0008', 'status' => Invoice::STATUS_PARTIALLY_PAID]), $this->legal()));
    }

    public function test_no_code_for_credit_notes(): void {
        $invoice = $this->invoice(['type' => Invoice::TYPE_CREDIT_NOTE]);

        $this->assertNull(app(GirocodeService::class)->payload($invoice, $this->legal()));
    }

    public function test_pdf_shows_the_code_and_the_bank_block_stays(): void {
        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($this->invoice()))->render();

        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('DE02120300000000202051', $html, 'Der Textblock bleibt neben dem Code stehen.');
    }

    public function test_pdf_without_the_code_still_shows_the_bank_block(): void {
        Setting::reset('invoicing.girocode_enabled', SettingScope::Organization, $this->org);

        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($this->invoice()))->render();

        $this->assertStringNotContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('DE02120300000000202051', $html);
    }
}
