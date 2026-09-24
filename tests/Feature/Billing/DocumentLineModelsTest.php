<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLineModelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Audit\AuditLog;
use App\Models\Contracts\{DocumentLine, HasDocumentLines};
use App\Models\Costing\{CostEstimate, CostEstimateItem};
use App\Models\Finance\{BillingTransfer, BillingTransferItem, BillingTransferPosition};
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Invoicing\{Invoice, InvoiceItem, InvoiceSchedule, InvoiceScheduleItem};
use App\Models\Platform\{Organization, User};
use App\Models\Sales\{Quote, QuoteItem};
use App\Services\Invoicing\TaxResolver;
use App\Support\MorphMap;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Positionsvertrag an der Datenbank (MVP-865): Factories, Audit, Sqid, Kopfrelation, Summen. */
class DocumentLineModelsTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->app->instance('currentOrganization', $this->organization);
        $this->actingAs(User::factory()->create(['organization_id' => $this->organization->id]));
    }

    /** @return iterable<string, array{\Closure(): Model}> */
    public static function lineModels(): iterable {
        yield 'invoice_items' => [fn (): Model => InvoiceItem::factory()->create()];
        yield 'quote_items' => [fn (): Model => QuoteItem::factory()->create()];
        yield 'invoice_schedule_items' => [fn (): Model => InvoiceScheduleItem::factory()->create()];
        yield 'cost_estimate_items' => [fn (): Model => CostEstimateItem::factory()->create()];
        yield 'billing_transfer_items' => [fn (): Model => BillingTransferItem::factory()->create()];
        yield 'billing_transfer_positions' => [fn (): Model => BillingTransferPosition::factory()->create()];
        yield 'boq_items' => [fn (): Model => BoqItem::factory()->create()];
    }

    /** @param \Closure(): Model $create */
    #[\PHPUnit\Framework\Attributes\DataProvider('lineModels')]
    public function test_line_models_share_factory_audit_sqid_and_contract(\Closure $create): void {
        $line = $create();
        $fresh = $line->fresh();
        $this->assertInstanceOf(DocumentLine::class, $fresh);
        $this->assertInstanceOf(HasDocumentLines::class, $fresh->lineDocument()->first());
        $this->assertSame('sqid', $fresh->getRouteKeyName());
        $this->assertInstanceOf(Money::class, $fresh->netAmount());
        $this->assertTrue($fresh->grossAmount()->greaterThanOrEqual($fresh->netAmount()));
        $audited = AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey($line::class))
            ->where('auditable_id', $line->getKey())
            ->where('event', 'created')
            ->exists();
        $this->assertTrue($audited, $line::class . ' schreibt kein Audit-Protokoll.');
    }

    public function test_invoice_lines_and_totals_come_from_the_database(): void {
        $invoice = Invoice::factory()->create(['tax_rate' => '19.00']);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position' => 1, 'quantity' => '2.000', 'unit_price' => '100.0000', 'tax_rate' => '19.00']);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'position' => 2, 'quantity' => '1.000', 'unit_price' => '50.0000', 'discount_percent' => '10.00', 'tax_rate' => '7.00']);

        $invoice = $invoice->fresh(['items']);
        $this->assertNotNull($invoice);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame(['200.00', '45.00'], $invoice->lines->map(fn (InvoiceItem $i): string => $i->netAmount()->getAmount())->all());

        $totals = $invoice->documentTotals();
        $this->assertSame('245.00', $totals['subtotal']->getAmount());
        $this->assertSame('41.15', $totals['tax_amount']->getAmount());
        $this->assertSame('286.15', $totals['total']->getAmount());
        $this->assertSame(CurrencyCode::Euro, $invoice->lines->first()?->lineCurrency());
    }

    public function test_heads_expose_lines_in_document_order(): void {
        $quote = Quote::factory()->create();
        QuoteItem::factory()->create(['quote_id' => $quote->id, 'position' => 2, 'unit_price' => '10.00']);
        QuoteItem::factory()->create(['quote_id' => $quote->id, 'position' => 1, 'unit_price' => '20.00']);
        $this->assertSame([1, 2], $quote->lines()->get()->map(fn (QuoteItem $i): int => $i->linePosition())->all());
        $this->assertSame('35.70', $quote->fresh(['items'])?->documentTotals()['total']->getAmount());

        $estimate = CostEstimate::factory()->create(['currency' => 'CHF']);
        CostEstimateItem::factory()->create(['cost_estimate_id' => $estimate->id, 'quantity' => '3.0000', 'unit_price' => '10.5000', 'amount' => null]);
        $this->assertSame(CurrencyCode::SwissFranc, $estimate->documentCurrency());
        $this->assertSame('31.50', $estimate->fresh(['items'])?->documentTotals()['total']->getAmount());

        $transfer = BillingTransfer::factory()->create();
        BillingTransferPosition::factory()->create(['billing_transfer_id' => $transfer->id, 'quantity' => '2.000', 'unit_price' => '50.0000', 'vat_rate' => '19.00', 'amount' => '100.00']);
        $this->assertInstanceOf(BillingTransferPosition::class, $transfer->lines()->first());
        $this->assertSame('119.00', $transfer->fresh(['positions'])?->documentTotals()['total']->getAmount());

        $boq = BillOfQuantity::factory()->create();
        BoqItem::factory()->create(['bill_of_quantity_id' => $boq->id, 'position' => 1, 'quantity' => '4.0000', 'unit_price' => '25.0000', 'vat_rate' => '19.00']);
        BoqItem::factory()->create(['bill_of_quantity_id' => $boq->id, 'position' => 2, 'type' => 'optional', 'quantity' => '1.0000', 'unit_price' => '999.0000', 'vat_rate' => '19.00']);
        $this->assertSame('119.00', $boq->fresh(['items'])?->documentTotals()['total']->getAmount(), 'Eventualpositionen zählen nicht zur LV-Summe.');
    }

    public function test_schedule_totals_fall_back_to_the_customers_tax_rate(): void {
        $schedule = InvoiceSchedule::factory()->create();
        InvoiceScheduleItem::factory()->create(['invoice_schedule_id' => $schedule->id, 'quantity' => '1.000', 'unit_price' => '100.0000', 'tax_rate' => null]);

        $schedule = $schedule->fresh(['items', 'customer']);
        $this->assertNotNull($schedule);
        $customer = $schedule->customer;
        $this->assertNotNull($customer);
        $rate = (float) app(TaxResolver::class)->resolve($this->organization, $customer)['rate'];
        $totals = $schedule->documentTotals();

        $this->assertSame('100.00', $totals['subtotal']->getAmount());
        $this->assertSame(Money::of('100.00', CurrencyCode::Euro)->percentage($rate)->getAmount(), $totals['tax_amount']->getAmount());
    }
}
