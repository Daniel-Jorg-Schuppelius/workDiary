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

    public function test_no_code_for_a_paid_invoice(): void {
        $this->assertNull(app(GirocodeService::class)->payload($this->invoice(['status' => Invoice::STATUS_PAID]), $this->legal()));
    }

    public function test_partial_payment_without_a_known_amount_gets_no_code(): void {
        // Status gesetzt, aber weder Zuordnung noch Kassenbeleg: Der Rest ist
        // unbekannt — ein Code über die volle Summe lüde zur Doppelzahlung ein.
        $invoice = $this->invoice(['number' => 'R2030-0008', 'status' => Invoice::STATUS_PARTIALLY_PAID]);

        $this->assertNull(app(GirocodeService::class)->payload($invoice, $this->legal()));
    }

    public function test_partial_payment_puts_the_remainder_into_the_code(): void {
        $invoice = $this->invoice(['number' => 'R2030-0009', 'status' => Invoice::STATUS_PARTIALLY_PAID]);
        $transaction = \App\Models\Finance\BankTransaction::factory()->create([
            'organization_id' => $this->org->id,
            'amount' => '50.00',
        ]);
        \App\Models\Finance\PaymentAllocation::query()->create([
            'organization_id' => $this->org->id,
            'bank_transaction_id' => $transaction->id,
            'allocatable_type' => Invoice::class,
            'allocatable_id' => $invoice->id,
            'amount' => '50.00',
            'kind' => \App\Enums\Finance\AllocationKind::Payment->value,
        ]);

        $payload = (string) app(GirocodeService::class)->payload($invoice->fresh(), $this->legal());

        // 119,00 Rechnung − 50,00 zugeordnet.
        $this->assertStringContainsString('EUR69.00', $payload);
    }

    public function test_cash_payments_count_towards_the_remainder(): void {
        $invoice = $this->invoice(['number' => 'R2030-0010', 'status' => Invoice::STATUS_PARTIALLY_PAID]);
        $register = \App\Models\CashRegister::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Ladenkasse',
            'currency' => 'EUR',
            'opening_balance' => '0.00',
            'opened_on' => now()->toDateString(),
            'active' => true,
        ]);
        // Über den Kassenbuch-Service, nicht direkt: cash_entries sind
        // hash-verkettet und tragen eine Registernummer.
        app(\App\Services\Finance\CashBookService::class)->record($register, [
            'booked_on' => now()->toDateString(),
            'direction' => \App\Models\CashEntry::DIRECTION_IN,
            'amount' => 19.00,
            'purpose' => 'Teilzahlung bar',
            'invoice_id' => $invoice->id,
        ]);

        $payload = (string) app(GirocodeService::class)->payload($invoice->fresh(), $this->legal());

        $this->assertStringContainsString('EUR100.00', $payload);
    }

    public function test_open_retention_reduces_the_coded_amount(): void {
        // Der Beleg weist den geminderten Zahlbetrag aus — der Code muss
        // dieselbe Zahl tragen, sonst überweist der Kunde den Einbehalt mit.
        $invoice = $this->invoice(['number' => 'R2030-0011', 'status' => Invoice::STATUS_DRAFT]);
        app(\App\Services\Invoicing\RetentionService::class)->add(
            $invoice,
            \App\Enums\Invoicing\RetentionKind::Warranty,
            percent: 5.0,
            fixedAmount: null,
            dueOn: null,
            actor: $this->user,
        );

        $invoice->forceFill(['status' => Invoice::STATUS_ISSUED])->save();

        $payload = (string) app(GirocodeService::class)->payload($invoice->fresh(), $this->legal());

        // 119,00 brutto − 5 % der Nettosumme (5,00) = 114,00.
        $this->assertStringContainsString('EUR114.00', $payload);
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
