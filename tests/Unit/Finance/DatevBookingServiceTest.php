<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Enums\Finance\ChartOfAccounts;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Finance\DatevBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Buchungssatz-Bildung (Feature 045, Priorität 2): Soll/Haben, Konto-Mapping je
 * Steuersatz, Gutschrift-Umkehrung, Debitornummer-Vergabe und Preflight-Fehler.
 */
class DatevBookingServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): DatevBookingService {
        return new DatevBookingService();
    }

    private function config(array $overrides = []): DatevBookingConfig {
        $this->organization->settings = ['datev' => array_merge([
            'advisor_number' => 12345,
            'client_number' => 1,
            'skr' => 'skr03',
            'debtor_base' => 10000,
        ], $overrides)];

        return DatevBookingConfig::forOrganization($this->organization);
    }

    private function makeCustomer(array $attrs = []): Customer {
        return Customer::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $attrs));
    }

    private function makeInvoice(Customer $customer, array $attrs = []): Invoice {
        return Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'RE-2026-0001',
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ], $attrs));
    }

    public function test_invoice_booking_uses_debit_on_debtor_against_revenue_with_tax_key(): void {
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer);

        $rows = $this->service()->buildBookingRows(new Collection([$invoice]), $this->config());

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('S', $row['soll_haben']);                       // Soll Debitor
        $this->assertSame('8400', $row['revenue_account']);               // SKR03 19% Erlöskonto
        $this->assertSame('3', $row['tax_key']);                          // 19% ⇒ BU 3
        $this->assertSame(119.0, $row['amount']);                         // Brutto
        $this->assertSame('RE-2026-0001', $row['document_ref']);
        $this->assertFalse($row['is_credit_note']);
    }

    public function test_seven_percent_maps_to_tax_key_two(): void {
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer, ['tax_rate' => '7.00', 'tax_amount' => '7.00', 'total' => '107.00']);

        $rows = $this->service()->buildBookingRows(new Collection([$invoice]), $this->config());

        $this->assertSame('2', $rows[0]['tax_key']);
        $this->assertSame(107.0, $rows[0]['amount']);
    }

    public function test_tax_free_uses_dedicated_revenue_account_and_key_zero(): void {
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer, ['tax_rate' => '0.00', 'tax_amount' => '0.00', 'total' => '100.00']);

        $rows = $this->service()->buildBookingRows(new Collection([$invoice]), $this->config());

        $this->assertSame('8200', $rows[0]['revenue_account']);  // SKR03 steuerfrei
        $this->assertSame('0', $rows[0]['tax_key']);
    }

    /**
     * MVP-1623: Ohne Steuer entscheidet der Steuerfall des Belegs über Erlöskonto
     * und Schlüssel — dieselbe Kategorie wie der Journal-Weg, Konten aus der
     * Vorlage; auf Automatikkonten kein Schlüssel und keine Preflight-Warnung.
     */
    public function test_zero_rated_sales_book_on_the_account_of_their_tax_case(): void {
        $eu = $this->makeCustomer(['country' => 'AT', 'vat_id' => 'ATU12345678']);
        $domestic = $this->makeCustomer(['country' => 'DE', 'is_reverse_charge_recipient' => true]);
        $abroad = $this->makeCustomer(['country' => 'CH']);
        $zero = ['tax_rate' => '0.00', 'tax_amount' => '0.00', 'total' => '100.00'];
        $cases = [
            'IGL' => [$eu, Invoice::CATEGORY_MATERIAL, 'AE', '8125', '4125'],
            'RC21' => [$eu, Invoice::CATEGORY_SERVICE, 'AE', '8336', '4336'],
            'RC60' => [$domestic, Invoice::CATEGORY_SERVICE, 'AE', '8337', '4337'],
            'AUSF' => [$abroad, Invoice::CATEGORY_MATERIAL, 'G', '8120', '4120'],
            'NSTB' => [$abroad, Invoice::CATEGORY_SERVICE, 'G', '8338', '4338'],
        ];

        $n = 0;
        foreach ($cases as $code => [$customer, $category, $frozen, $skr03, $skr04]) {
            $invoice = $this->makeInvoice($customer, $zero + [
                'number' => 'RE-0-' . ++$n,
                'category' => $category,
                'is_reverse_charge' => $frozen === 'AE',
                'tax_context' => ['category' => $frozen],
            ]);

            $row = $this->service()->buildBookingRows(new Collection([$invoice]), $this->config())[0];
            $this->assertSame([$skr03, null, true], [$row['revenue_account'], $row['tax_key'], $row['automatic']], $code);
            $this->assertSame($skr04, $this->service()->buildBookingRows(new Collection([$invoice]), $this->config(['skr' => 'skr04']))[0]['revenue_account'], $code);
            $this->assertSame([], $this->service()->preflight($this->organization, new Collection([$invoice]), $this->config())['warnings'], $code);
        }

        // Steuerfrei im Inland bleibt beim eingestellten Konto.
        $exempt = $this->makeInvoice($this->makeCustomer(), $zero + ['number' => 'RE-0-E', 'tax_context' => ['category' => 'E']]);
        $row = $this->service()->buildBookingRows(new Collection([$exempt]), $this->config())[0];
        $this->assertSame(['8200', '0'], [$row['revenue_account'], $row['tax_key']]);
    }

    public function test_credit_note_reverses_soll_haben(): void {
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer, [
            'number' => 'GS-2026-0001',
            'type' => Invoice::TYPE_CREDIT_NOTE,
        ]);

        $rows = $this->service()->buildBookingRows(new Collection([$invoice]), $this->config());

        $this->assertSame('H', $rows[0]['soll_haben']);          // Gutschrift ⇒ Haben Debitor
        $this->assertTrue($rows[0]['is_credit_note']);
    }

    public function test_explicit_debtor_number_takes_precedence_over_range_rule(): void {
        $config = $this->config();
        $customer = $this->makeCustomer(['debtor_no' => '20055']);
        $invoice = $this->makeInvoice($customer);

        $rows = $this->service()->buildBookingRows(new Collection([$invoice]), $config);
        $this->assertSame('20055', $rows[0]['debtor_account']);
    }

    public function test_derived_debtor_number_is_base_plus_customer_id(): void {
        $config = $this->config(['debtor_base' => 10000]);
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer);

        $rows = $this->service()->buildBookingRows(new Collection([$invoice]), $config);
        $this->assertSame((string) (10000 + (int) $customer->id), $rows[0]['debtor_account']);
    }

    public function test_skr04_default_revenue_account_is_4400(): void {
        $config = $this->config(['skr' => 'skr04']);
        $this->assertSame(ChartOfAccounts::Skr04, $config->skr);
        $this->assertSame('4400', $config->revenueAccountFor(19.0));
        $this->assertSame('4200', $config->revenueAccountFor(0.0));
    }

    public function test_preflight_errors_on_missing_client_numbers(): void {
        $config = $this->config(['advisor_number' => 0, 'client_number' => 0]);
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer);

        $result = $this->service()->preflight($this->organization, new Collection([$invoice]), $config);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_preflight_warns_on_unknown_tax_key(): void {
        $config = $this->config();
        $customer = $this->makeCustomer();
        $invoice = $this->makeInvoice($customer, ['tax_rate' => '5.50', 'tax_amount' => '5.50', 'total' => '105.50']);

        $result = $this->service()->preflight($this->organization, new Collection([$invoice]), $config);
        $this->assertNotEmpty($result['warnings']);
        $this->assertSame(105.50, $result['total']);
    }
}
