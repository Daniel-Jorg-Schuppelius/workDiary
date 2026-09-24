<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTotalsFixtureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Costing\{CostEstimate, CostEstimateItem};
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Invoicing\{Invoice, InvoiceItem, InvoiceSchedule, InvoiceScheduleItem};
use App\Models\Sales\{Quote, QuoteItem};
use App\Observers\InvoiceItemObserver;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Eingefrorene Belegsummen (MVP-865): die Fixtures unter
 * tests/Fixtures/totals wurden VOR der Umstellung auf den
 * DocumentTotalsCalculator mit dem alten Rechner erzeugt — Zeilennetto,
 * Satzgruppen, Belegrabatt, Skonto und Summen müssen byte-gleich bleiben.
 * Belege entstehen im Speicher, ohne Datenbank.
 */
class DocumentTotalsFixtureTest extends TestCase {
    /** @return iterable<string, array{string}> */
    public static function fixtures(): iterable {
        foreach (glob(__DIR__ . '/../../Fixtures/totals/*.json') ?: [] as $file) {
            yield basename($file, '.json') => [$file];
        }
    }

    #[DataProvider('fixtures')]
    public function test_totals_match_the_frozen_fixture(string $file): void {
        /** @var array{kind: string, document: array<string, mixed>, lines: list<array<string, mixed>>, expected: array<string, mixed>} $fixture */
        $fixture = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        $actual = match ($fixture['kind']) {
            'invoice' => $this->invoice($fixture['document'], $fixture['lines']),
            'quote' => $this->quote($fixture['lines']),
            'invoice_schedule' => $this->schedule($fixture['lines']),
            'billing_transfer' => $this->transfer($fixture['lines']),
            'cost_estimate' => $this->costEstimate($fixture['document'], $fixture['lines']),
            'boq' => $this->boq($fixture['document'], $fixture['lines']),
            default => throw new \InvalidArgumentException("Unbekannte Belegart {$fixture['kind']}"),
        };

        $this->assertSame($this->normalize($fixture['expected']), $this->normalize($actual), basename($file));
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function invoice(array $document, array $lines): array {
        $invoice = new Invoice;
        $invoice->forceFill($document);
        $items = $this->lines(InvoiceItem::class, 'invoice', $invoice, $lines, fn (int $i): array => ['position' => $i, 'description' => "Zeile {$i}"]);
        $observer = app(InvoiceItemObserver::class);
        foreach ($items as $item) {
            $observer->saving($item);
        }
        $invoice->setRelation('items', $items);
        $invoice->recalculate();
        $totals = $invoice->documentTotals();

        return [
            'line_nets' => $items->map(fn (InvoiceItem $i): string => $i->netAmount()->getAmount())->values()->all(),
            'line_net_sum' => $totals['line_net_sum']->getAmount(),
            'document_discount' => $totals['document_discount']->getAmount(),
            'by_rate' => $this->byRate($totals['by_rate']),
            'subtotal' => (string) $invoice->subtotal?->getAmount(),
            'tax_amount' => (string) $invoice->tax_amount?->getAmount(),
            'total' => (string) $invoice->total?->getAmount(),
            'tax_breakdown' => $invoice->tax_breakdown,
            'line_subtotal' => $invoice->lineSubtotal()->getAmount(),
            'document_discount_total' => $invoice->documentDiscountTotal()->getAmount(),
            'skonto_amount' => $invoice->skontoAmount()->getAmount(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function quote(array $lines): array {
        $quote = new Quote;
        $items = $this->lines(QuoteItem::class, 'quote', $quote, $lines, fn (int $i): array => ['position' => $i, 'description' => "Zeile {$i}"]);
        $quote->setRelation('items', $items);
        $quote->recalculate();

        return [
            'line_nets' => $items->map(fn (QuoteItem $i): string => $i->netAmount()->getAmount())->values()->all(),
            'tax_breakdown' => array_map(fn (array $row): array => ['rate' => $row['rate'], 'net' => $row['net']->getAmount(), 'tax' => $row['tax']->getAmount()], $quote->taxBreakdownByRate()),
            'subtotal' => (string) $quote->subtotal?->getAmount(),
            'tax_amount' => (string) $quote->tax_amount?->getAmount(),
            'total' => (string) $quote->total?->getAmount(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function schedule(array $lines): array {
        $schedule = new InvoiceSchedule;
        $items = $this->lines(InvoiceScheduleItem::class, 'schedule', $schedule, $lines, fn (int $i): array => ['position' => $i, 'description' => "Zeile {$i}"]);
        $schedule->setRelation('items', $items);

        return ['line_nets' => $items->map(fn (InvoiceScheduleItem $i): string => $i->netAmount()->getAmount())->values()->all()]
            + $this->documentTotals($schedule->documentTotals());
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function transfer(array $lines): array {
        $transfer = new BillingTransfer;
        $positions = $this->lines(BillingTransferPosition::class, 'transfer', $transfer, $lines, fn (int $i): array => ['position' => $i, 'name' => "Position {$i}", 'source_kind' => BillingTransferPosition::KIND_TIME]);
        $transfer->setRelation('positions', $positions);

        return ['line_nets' => $positions->map(fn (BillingTransferPosition $p): string => $p->netAmount()->getAmount())->values()->all()]
            + $this->documentTotals($transfer->documentTotals());
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function costEstimate(array $document, array $lines): array {
        $estimate = new CostEstimate;
        $estimate->forceFill($document);
        $items = $this->lines(CostEstimateItem::class, 'estimate', $estimate, $lines, fn (int $i): array => ['position' => $i, 'level' => 1]);
        $estimate->setRelation('items', $items);

        return ['line_nets' => $items->map(fn (CostEstimateItem $i): string => $i->netAmount()->getAmount())->values()->all()]
            + $this->documentTotals($estimate->documentTotals());
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function boq(array $document, array $lines): array {
        $boq = new BillOfQuantity;
        $boq->forceFill($document);
        $items = $this->lines(BoqItem::class, 'billOfQuantity', $boq, $lines, fn (int $i): array => ['position' => $i, 'reference_no' => sprintf('01.%04d', $i * 10)]);
        $boq->setRelation('items', $items);

        return ['line_nets' => $items->map(fn (BoqItem $i): string => $i->netAmount()->getAmount())->values()->all()]
            + $this->documentTotals($boq->documentTotals());
    }

    /**
     * @template TLine of Model
     *
     * @param  class-string<TLine>  $class
     * @param  list<array<string, mixed>>  $lines
     * @param  \Closure(int): array<string, mixed>  $extra
     * @return \Illuminate\Database\Eloquent\Collection<int, TLine>
     */
    private function lines(string $class, string $relation, Model $document, array $lines, \Closure $extra): \Illuminate\Database\Eloquent\Collection {
        $items = new \Illuminate\Database\Eloquent\Collection;
        foreach ($lines as $index => $line) {
            $item = new $class;
            $item->setRelation($relation, $document);
            $item->forceFill($line + $extra($index + 1));
            $items->push($item);
        }

        return $items;
    }

    /**
     * @param  array{line_net_sum: Money, document_discount: Money, by_rate: array<string, array{rate: float, net: Money, allowance: Money, taxable: Money, tax: Money}>, subtotal: Money, tax_amount: Money, total: Money}  $totals
     * @return array<string, mixed>
     */
    private function documentTotals(array $totals): array {
        return [
            'line_net_sum' => $totals['line_net_sum']->getAmount(),
            'document_discount' => $totals['document_discount']->getAmount(),
            'by_rate' => $this->byRate($totals['by_rate']),
            'subtotal' => $totals['subtotal']->getAmount(),
            'tax_amount' => $totals['tax_amount']->getAmount(),
            'total' => $totals['total']->getAmount(),
        ];
    }

    /**
     * @param  array<string, array{rate: float, net: Money, allowance: Money, taxable: Money, tax: Money}>  $byRate
     * @return array<string, array<string, float|string>>
     */
    private function byRate(array $byRate): array {
        $rows = [];
        foreach ($byRate as $key => $group) {
            $rows[(string) $key] = ['rate' => $group['rate'], 'net' => $group['net']->getAmount(), 'allowance' => $group['allowance']->getAmount(), 'taxable' => $group['taxable']->getAmount(), 'tax' => $group['tax']->getAmount()];
        }

        return $rows;
    }

    /** JSON kennt keine Float-Ganzzahlen (19.0 → 19): Zahlen einheitlich als float vergleichen. */
    private function normalize(mixed $value): mixed {
        if (is_array($value)) {
            return array_map(fn (mixed $v): mixed => $this->normalize($v), $value);
        }

        return is_int($value) || is_float($value) ? (float) $value : $value;
    }
}
