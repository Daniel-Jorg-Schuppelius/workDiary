<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqBillingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\{CashEntry, PaymentAllocation};
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Invoicing\Invoice;
use App\Services\Invoicing\{DunningService, InvoiceGenerator};
use App\Support\MorphMap;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rechnungspakete je LV (MVP-932): Abschlag aus dem bewerteten Leistungsstand
 * (kumuliert, bisherige Abschläge abgesetzt) über die Abschlagsrechnung der
 * Faktura; Rechnungen und Zahlungen je LV.
 */
final class BoqBillingService {
    public function __construct(
        private readonly BoqCostingService $costing,
        private readonly InvoiceGenerator $invoices,
        private readonly DunningService $dunning,
    ) {}

    /** @return Collection<int, Invoice> */
    public function invoices(BillOfQuantity $boq): Collection {
        return Invoice::query()->where('bill_of_quantity_id', $boq->id)->orderBy('id')->get();
    }

    /** Netto der bisherigen Abschläge; Entwürfe zählen mit, damit kein zweiter Abschlag dieselbe Leistung stellt. */
    public function downPaymentsNet(BillOfQuantity $boq): float {
        return round((float) Invoice::query()
            ->where('bill_of_quantity_id', $boq->id)
            ->where('type', Invoice::TYPE_DOWN_PAYMENT)
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->sum('subtotal'), 2);
    }

    /** @return array{executed: float, previous: float, amount: float, progress: float, currency: string} */
    public function proposal(BillOfQuantity $boq): array {
        $summary = $this->costing->summarize($boq);
        $previous = $this->downPaymentsNet($boq);

        return [
            'executed' => $summary['executed'],
            'previous' => $previous,
            'amount' => round(max(0.0, $summary['executed'] - $previous), 2),
            'progress' => $summary['progress'],
            'currency' => $summary['currency'],
        ];
    }

    public function createDownPayment(BillOfQuantity $boq, float $amount): Invoice {
        $boq->loadMissing('project.customer');
        $customer = $boq->project?->customer;
        if (! $customer instanceof Customer) {
            throw ValidationException::withMessages(['amount' => __('gaeb.billing.error.no_customer')]);
        }
        if ($customer->currency !== $boq->currency) {
            throw ValidationException::withMessages(['amount' => __('gaeb.billing.error.currency')]);
        }

        return DB::transaction(function () use ($boq, $customer, $amount): Invoice {
            BillOfQuantity::query()->whereKey($boq->id)->lockForUpdate()->first();
            $proposal = $this->proposal($boq);
            $amount = round($amount, 2);
            if ($amount <= 0.0 || $amount > $proposal['amount']) {
                throw ValidationException::withMessages(['amount' => __('gaeb.billing.error.amount', ['max' => NumberHelper::toGermanFormat($proposal['amount'], 2, withThousandsSeparator: true)])]);
            }
            $count = Invoice::query()->where('bill_of_quantity_id', $boq->id)->where('type', Invoice::TYPE_DOWN_PAYMENT)->where('status', '!=', InvoiceStatus::Cancelled)->count();
            $invoice = $this->invoices->downPaymentFor(
                $customer,
                $boq->project,
                (string) __('gaeb.billing.line', ['no' => $count + 1, 'boq' => $boq->name, 'percent' => (int) round($proposal['progress'] * 100)]),
                NumberHelper::toUSFormat($amount, 2),
                now(),
            );
            $invoice->forceFill(['bill_of_quantity_id' => $boq->id])->save();

            return $invoice;
        });
    }

    /**
     * @return array{rows: list<array{invoice: Invoice, paid: float, open: float}>, payments: list<array{date: ?\Carbon\CarbonInterface, amount: float, source: string, invoice: Invoice}>, totals: array{net: float, gross: float, paid: float, open: float}}
     */
    public function history(BillOfQuantity $boq): array {
        $invoices = $this->invoices($boq)->keyBy('id');
        $rows = [];
        $totals = ['net' => 0.0, 'gross' => 0.0, 'paid' => 0.0, 'open' => 0.0];
        foreach ($invoices as $invoice) {
            $active = $invoice->status !== InvoiceStatus::Cancelled && $invoice->status !== InvoiceStatus::Draft;
            $paid = $active ? $this->dunning->paidAmount($invoice)->toFloat() : 0.0;
            $open = $active ? $this->dunning->openAmount($invoice)->toFloat() : 0.0;
            $rows[] = ['invoice' => $invoice, 'paid' => $paid, 'open' => $open];
            if ($active) {
                $totals['net'] += $invoice->subtotal?->toFloat() ?? 0.0;
                $totals['gross'] += $invoice->total?->toFloat() ?? 0.0;
                $totals['paid'] += $paid;
                $totals['open'] += $open;
            }
        }

        $payments = [];
        if ($invoices->isNotEmpty()) {
            $allocations = PaymentAllocation::query()
                ->where('allocatable_type', MorphMap::alias(Invoice::class))
                ->whereIn('allocatable_id', $invoices->keys())
                ->with('transaction')
                ->get();
            foreach ($allocations as $allocation) {
                $invoice = $invoices->get($allocation->allocatable_id);
                if ($invoice instanceof Invoice) {
                    $payments[] = ['date' => $allocation->transaction?->booking_date, 'amount' => (float) $allocation->amount, 'source' => $allocation->kind->label(), 'invoice' => $invoice];
                }
            }
            foreach (CashEntry::query()->whereIn('invoice_id', $invoices->keys())->where('direction', CashEntry::DIRECTION_IN)->get() as $entry) {
                $invoice = $invoices->get((int) $entry->invoice_id);
                if ($invoice instanceof Invoice) {
                    $payments[] = ['date' => $entry->booked_on, 'amount' => $entry->amount?->toFloat() ?? 0.0, 'source' => (string) __('gaeb.billing.cash'), 'invoice' => $invoice];
                }
            }
            usort($payments, static fn (array $a, array $b): int => ($a['date']?->getTimestamp() ?? 0) <=> ($b['date']?->getTimestamp() ?? 0));
        }

        return ['rows' => $rows, 'payments' => $payments, 'totals' => array_map(static fn (float $v): float => round($v, 2), $totals)];
    }
}
