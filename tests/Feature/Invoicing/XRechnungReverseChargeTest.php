<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungReverseChargeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\{EInvoiceValidationService, XRechnungGenerator};
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use ERechnungToolkit\Validators\KositValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623: XRechnung bei Steuerschuld des Leistungsempfängers im Inland
 * (§ 13b Abs. 5 UStG). Geprüft wird über den KoSIT-Validator des
 * php-erechnung-toolkit (BR-AE-01 bis BR-AE-10), nicht über eine eigene
 * Nachbildung der Regeln.
 */
final class XRechnungReverseChargeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispielbau GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispielbau.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispielbau GmbH',
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    private function customer(?string $vatId): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Generalunternehmer Beispiel GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@gu.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'vat_id' => $vatId,
            'is_reverse_charge_recipient' => true,
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    /** Freier Entwurf über den TaxResolver; eine Position trägt den Regelsatz des Artikels. */
    private function issuedInvoice(Customer $customer): Invoice {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
        $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Trockenbau Wohnung 3', 'quantity' => '10', 'unit' => 'm²', 'unit_price' => '45.00', 'tax_rate' => '19.00', 'service_date' => '2026-10-15', 'position' => 1]);
        $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Montage', 'quantity' => '2', 'unit' => 'Std.', 'unit_price' => '60.00', 'service_date' => '2026-10-15', 'position' => 2]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
    }

    public function test_domestic_reverse_charge_xrechnung_passes_the_kosit_rules(): void {
        $invoice = $this->issuedInvoice($this->customer('DE811569869'));
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertTrue($invoice->is_reverse_charge);
        $this->assertSame('0.00', $invoice->tax_amount?->getAmount());

        $xml = app(XRechnungGenerator::class)->generate($invoice);
        // Kategorie AE mit 0 % in jeder Zeile und im Steueraufriss, Befreiungsgrund und Käufer-USt-IdNr.
        $this->assertStringNotContainsString('>19.00</cbc:Percent>', $xml);
        $this->assertStringContainsString('VATEX-EU-AE', $xml);
        $this->assertStringContainsString('DE811569869', $xml);

        $kosit = new KositValidator;
        if (! $kosit->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator (Java + JAR) ist nicht verfügbar.');
        }
        $result = $kosit->validate($xml);
        $this->assertTrue($result->isAccepted(), implode("\n", array_map('strval', $result->getErrors())));
    }

    /** BR-AE-02: ohne USt-IdNr. des Käufers (und ohne Registerkennung) keine gültige AE-Rechnung — der Preflight sagt es vor dem Versand. */
    public function test_reverse_charge_without_buyer_vat_id_fails_the_preflight(): void {
        $invoice = $this->issuedInvoice($this->customer(null));

        $report = app(EInvoiceValidationService::class)->validate($invoice);

        $this->assertContains((string) __('invoicing.einvoice.error.reverse_charge_buyer_vat_id'), $report['preflight_errors']);
    }
}
