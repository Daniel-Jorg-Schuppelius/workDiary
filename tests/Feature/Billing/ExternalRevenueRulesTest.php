<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalRevenueRulesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Plugins\Lexoffice\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceQuery};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Eine Belegregel für alle Auswertungen (MVP-1114): Lexoffice-Spiegel → ExternalInvoice. */
final class ExternalRevenueRulesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(string $number, array $attributes = []): LexofficeVoucher {
        return LexofficeVoucher::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'external_id' => 'ext-' . $number,
            'customer_id' => $this->customer->id,
            'voucher_type' => 'invoice',
            'voucher_status' => 'open',
            'voucher_number' => $number,
            'voucher_date' => '2026-06-10',
            'total_amount' => '119.00',
            'currency' => 'EUR',
        ]);
    }

    /** @return array<string, ExternalInvoice> */
    private function invoices(?ExternalInvoiceQuery $query = null): array {
        $byNumber = [];
        foreach (app(ExternalRevenue::class)->invoices($query ?? new ExternalInvoiceQuery) as $invoice) {
            $byNumber[$invoice->number] = $invoice;
        }

        return $byNumber;
    }

    public function test_vouchers_map_to_invoice_status_type_and_sign(): void {
        $this->voucher('OPEN', ['open_amount' => '119.00', 'due_date' => '2026-06-24']);
        $this->voucher('PART', ['open_amount' => '19.00']);
        $this->voucher('PAID', ['voucher_status' => 'paid', 'open_amount' => '0.00', 'paid_date' => '2026-06-20']);
        $this->voucher('GS', ['voucher_type' => 'creditnote', 'voucher_status' => 'paidoff', 'total_amount' => '23.80']);
        $this->voucher('AB', ['voucher_type' => 'downpaymentinvoice']);
        $this->voucher('ARCH', ['archived' => true]);
        $this->voucher('DRAFT', ['voucher_status' => 'draft']);
        $this->voucher('VOID', ['voucher_status' => 'voided']);
        $this->voucher('BUY', ['voucher_type' => 'purchaseinvoice']);

        $invoices = $this->invoices();

        $this->assertSame(['OPEN', 'PART', 'PAID', 'GS', 'AB', 'ARCH'], array_keys($invoices));
        $this->assertSame(InvoiceStatus::Issued, $invoices['OPEN']->status);
        $this->assertSame('2026-06-24', $invoices['OPEN']->dueOn?->toDateString());
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoices['PART']->status);
        $this->assertSame(19.0, $invoices['PART']->open);
        $this->assertSame(InvoiceStatus::Paid, $invoices['PAID']->status);
        $this->assertSame(0.0, $invoices['PAID']->open);
        $this->assertSame(Invoice::TYPE_CREDIT_NOTE, $invoices['GS']->type);
        $this->assertSame(-23.8, $invoices['GS']->gross);
        $this->assertSame(InvoiceStatus::Paid, $invoices['GS']->status);
        $this->assertSame(Invoice::TYPE_DOWN_PAYMENT, $invoices['AB']->type);
        $this->assertSame($this->customer->id, $invoices['OPEN']->customerId);
    }

    public function test_net_comes_from_the_voucher_or_its_lines(): void {
        $this->voucher('NET', ['net_amount' => '100.00']);
        $lines = $this->voucher('LINES');
        foreach (['60.00', '40.00'] as $i => $net) {
            LexofficeVoucherLine::query()->create([
                'organization_id' => $this->organization->id, 'voucher_id' => $lines->id, 'position' => $i + 1,
                'name' => 'Pos', 'quantity' => '1', 'unit_net' => $net, 'total_net' => $net, 'currency' => 'EUR',
            ]);
        }
        $this->voucher('NONE');

        $invoices = $this->invoices();

        $this->assertSame(100.0, $invoices['NET']->net);
        $this->assertSame(100.0, $invoices['LINES']->net);
        $this->assertNull($invoices['NONE']->net);
    }

    public function test_handed_over_local_invoices_count_only_locally(): void {
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'number' => 'RE-2026-7']);
        $this->voucher('RE-2026-7');
        $this->voucher('LX-1');

        $this->assertSame(['LX-1'], array_keys($this->invoices()));
    }

    public function test_aggregates_follow_the_same_rule(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->voucher('A', ['due_date' => '2026-06-01']);
        $this->voucher('B', ['voucher_type' => 'creditnote', 'total_amount' => '19.00']);
        $this->voucher('C', ['customer_id' => $other->id, 'voucher_status' => 'paid', 'due_date' => '2026-06-01']);
        $this->voucher('D', ['customer_id' => null, 'due_date' => '2026-06-01']);
        $this->voucher('E', ['voucher_type' => 'purchaseinvoice', 'due_date' => '2026-06-01']);
        $revenue = app(ExternalRevenue::class);

        $perCustomer = $revenue->perCustomer('2026-06-01', '2026-06-30');
        $this->assertSame(['count' => 2, 'total' => 100.0], $perCustomer[$this->customer->id]);
        $this->assertSame(119.0, $perCustomer[$other->id]['total']);
        $this->assertCount(2, $perCustomer, 'Belege ohne Kunden bleiben aus der Kundensumme.');
        $this->assertSame(['2026-06' => 100.0], $revenue->monthlyRevenue($this->customer->id, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31')));
        $this->assertSame(2, $revenue->overdueCount((int) $this->organization->id, '2026-06-15'), 'A und D: offen und fällig; bezahlt, Gutschrift und Einkauf zählen nicht.');
        $this->assertEqualsCanonicalizing([$this->customer->id, $other->id], $revenue->customerIdsWithDocuments((int) $this->organization->id));
    }

    public function test_query_filters_period_customer_and_open_items(): void {
        $this->voucher('JUNE');
        $this->voucher('JULY', ['voucher_date' => '2026-07-01']);
        $this->voucher('PAID', ['voucher_status' => 'paid']);
        $this->voucher('NOBODY', ['customer_id' => null]);

        $june = new ExternalInvoiceQuery(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));
        $this->assertSame(['JUNE', 'PAID', 'NOBODY'], array_keys($this->invoices($june)));
        $this->assertSame(['JUNE', 'JULY'], array_keys($this->invoices(new ExternalInvoiceQuery(customerId: $this->customer->id, openOnly: true))));
        $this->assertSame(['NOBODY'], array_keys($this->invoices(new ExternalInvoiceQuery(excludedCustomerIds: [(int) $this->customer->id]))));
    }
}
