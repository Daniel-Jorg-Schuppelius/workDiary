<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EInvoiceValidationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\EInvoice\EInvoiceValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 066, MVP-164: Validierungskette Preflight → XSD → KoSIT
 * (KoSIT-Verfügbarkeit transparent), Bericht-Route, Opt-in-Zwang vor
 * Ausstellung blockt fehlerhafte Rechnungen.
 */
final class EInvoiceValidationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create([
            'settings' => [
                'einvoice' => [
                    'seller_name' => 'WorkDiary Demo GmbH',
                    'street' => 'Beispielweg 2',
                    'zip' => '44135',
                    'city' => 'Dortmund',
                    'country' => 'DE',
                    'vat_id' => 'DE123456789',
                    'iban' => 'DE02120300000000202051',
                    'contact_name' => 'Daniel Demo',
                    'contact_email' => 'rechnung@workdiary.test',
                    'contact_phone' => '+49 231 123456',
                ],
            ],
        ]);
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'ACME GmbH',
            'address_street' => 'Werkstr. 1',
            'address_zip' => '44135',
            'address_city' => 'Dortmund',
            'email' => 'buchhaltung@acme.test',
            'buyer_reference' => '04011000-1234512345-06',
        ]);
    }

    private function issuedInvoice(): Invoice {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0042',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            'issued_on' => now(),
            'due_on' => now()->addDays(14),
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung',
            'quantity' => '2', 'unit' => 'h', 'unit_price' => '100.00', 'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh();
    }

    public function test_valid_invoice_passes_preflight_and_xsd(): void {
        $report = app(EInvoiceValidationService::class)->validate($this->issuedInvoice());

        $this->assertSame([], $report['preflight_errors']);
        $this->assertTrue($report['xml_generated']);
        $this->assertSame([], $report['schema_errors'], 'UBL-XSD muss bestehen: ' . implode('; ', $report['schema_errors']));
        // KoSIT hängt an Java/JAR — Verfügbarkeit wird transparent gemeldet.
        $this->assertIsBool($report['kosit_available']);
        if (! $report['kosit_available']) {
            $this->assertNull($report['kosit_valid']);
        }
        $this->assertTrue($report['valid'], 'KoSIT-Fehler: ' . implode(' | ', array_slice($report['kosit_errors'], 0, 8)));
    }

    public function test_incomplete_invoice_fails_preflight_without_xml(): void {
        $this->org->update(['settings' => []]); // Verkäuferdaten fehlen
        app()->instance('currentOrganization', $this->org->fresh());

        $report = app(EInvoiceValidationService::class)->validate($this->issuedInvoice());

        $this->assertNotSame([], $report['preflight_errors']);
        $this->assertFalse($report['xml_generated']);
        $this->assertFalse($report['valid']);
    }

    public function test_report_page_renders_and_enforce_blocks_issue(): void {
        $invoice = $this->issuedInvoice();

        $this->actingAs($this->user)
            ->get(route('invoices.einvoice-validation', $invoice))
            ->assertOk()
            ->assertSee(__('Fachlicher Preflight (§ 14 UStG, EN-16931-Kernfelder)'));

        // Opt-in-Zwang: kaputte Entwurfsrechnung darf nicht ausgestellt werden.
        $settings = (array) $this->org->settings;
        $settings['einvoice']['enforce_validation'] = '1';
        $settings['einvoice']['seller_name'] = ''; // Preflight-Fehler provozieren
        $this->org->update(['settings' => $settings]);
        app()->instance('currentOrganization', $this->org->fresh());

        $draft = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0043',
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
        ]);

        $this->actingAs($this->user)
            ->post(route('invoices.issue', $draft))
            ->assertRedirect(route('invoices.einvoice-validation', $draft));
        $this->assertSame(Invoice::STATUS_DRAFT, $draft->fresh()->status, 'Ausstellung wurde geblockt.');
    }
}
