<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCallOffService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\BoqCallOffStatus;
use App\Models\Customer\Customer;
use App\Models\Gaeb\{BillOfQuantity, BoqCallOff, BoqCallOffItem, BoqItem};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Invoicing\InvoiceGenerator;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zeitvertragsarbeiten (MVP-931): Abrufe aus einem Rahmen-LV. Die Menge je
 * Position ist durch den Rahmen begrenzt (stornierte Abrufe zählen nicht);
 * abgerechnet wird je Abruf über einen Rechnungsentwurf der Faktura mit dem
 * Einheitspreis der LV-Position.
 */
final class BoqCallOffService {
    use AssertsValidatedTransition;

    public function __construct(private readonly InvoiceGenerator $invoices) {}

    /** @return array<int, float> LV-Position → abgerufene Menge */
    public function calledQuantities(BillOfQuantity $boq): array {
        return BoqCallOffItem::query()
            ->whereHas('callOff', fn ($q) => $q->where('bill_of_quantity_id', $boq->id)->where('status', '!=', BoqCallOffStatus::Cancelled->value))
            ->selectRaw('boq_item_id, SUM(quantity) as called')
            ->groupBy('boq_item_id')
            ->pluck('called', 'boq_item_id')
            ->map(fn (mixed $v): float => round((float) $v, 4))
            ->all();
    }

    /** @return list<array{item: BoqItem, framework: float, called: float, remaining: float}> */
    public function remaining(BillOfQuantity $boq): array {
        $called = $this->calledQuantities($boq);
        $rows = [];
        foreach ($boq->items()->orderBy('position')->get() as $item) {
            if (! $item->type->isBillable()) {
                continue;
            }
            $framework = (float) ($item->quantity?->getNumericValue() ?? 0);
            $done = $called[$item->id] ?? 0.0;
            $rows[] = ['item' => $item, 'framework' => $framework, 'called' => $done, 'remaining' => round(max(0.0, $framework - $done), 4)];
        }

        return $rows;
    }

    /**
     * @param array{title: string, ordered_on?: ?string, due_on?: ?string, note?: ?string} $data
     * @param array<int|string, mixed> $quantities LV-Position (ID) → Menge
     */
    public function create(BillOfQuantity $boq, array $data, array $quantities, User $actor): BoqCallOff {
        if (! $boq->is_framework) {
            throw ValidationException::withMessages(['title' => __('gaeb.call_off.error.not_framework')]);
        }

        return DB::transaction(function () use ($boq, $data, $quantities, $actor): BoqCallOff {
            BillOfQuantity::query()->whereKey($boq->id)->lockForUpdate()->first();
            $remaining = collect($this->remaining($boq))->keyBy(fn (array $row): int => $row['item']->id);

            $lines = [];
            foreach ($quantities as $itemId => $raw) {
                $quantity = is_numeric($raw) ? round((float) $raw, 4) : 0.0;
                if ($quantity <= 0.0) {
                    continue;
                }
                $row = $remaining->get((int) $itemId);
                if ($row === null) {
                    throw ValidationException::withMessages(['quantities' => __('gaeb.call_off.error.foreign_item')]);
                }
                if ($quantity > $row['remaining']) {
                    throw ValidationException::withMessages(['quantities' => __('gaeb.call_off.error.exceeds', [
                        'item' => $row['item']->reference_no,
                        'remaining' => NumberHelper::toGermanFormat($row['remaining'], 3, withThousandsSeparator: true),
                    ])]);
                }
                $lines[(int) $itemId] = $quantity;
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['quantities' => __('gaeb.call_off.error.empty')]);
            }

            $callOff = BoqCallOff::query()->create([
                'organization_id' => $boq->organization_id,
                'bill_of_quantity_id' => $boq->id,
                'number' => (int) BoqCallOff::query()->where('bill_of_quantity_id', $boq->id)->max('number') + 1,
                'title' => $data['title'],
                'ordered_on' => $data['ordered_on'] ?? null,
                'due_on' => $data['due_on'] ?? null,
                'status' => BoqCallOffStatus::Draft,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);
            foreach ($lines as $itemId => $quantity) {
                $callOff->items()->create(['organization_id' => $boq->organization_id, 'boq_item_id' => $itemId, 'quantity' => NumberHelper::toUSFormat($quantity, 4)]);
            }

            return $callOff;
        });
    }

    public function transition(BoqCallOff $callOff, BoqCallOffStatus $to, User $actor): void {
        $this->assertValidatedTransition($callOff->status, $to, 'gaeb.call_off.error.transition');
        if ($to === BoqCallOffStatus::Cancelled && $callOff->activeInvoice() !== null) {
            throw ValidationException::withMessages(['status' => __('gaeb.call_off.error.invoiced')]);
        }
        $callOff->update(['status' => $to, 'updated_by' => $actor->id]);
    }

    public function invoice(BoqCallOff $callOff, User $actor): Invoice {
        if (! $callOff->status->isBillable()) {
            throw ValidationException::withMessages(['status' => __('gaeb.call_off.error.not_billable')]);
        }
        if ($callOff->activeInvoice() !== null) {
            throw ValidationException::withMessages(['status' => __('gaeb.call_off.error.invoiced')]);
        }
        $boq = $callOff->billOfQuantity()->with('project.customer')->firstOrFail();
        $customer = $boq->project?->customer;
        if (! $customer instanceof Customer) {
            throw ValidationException::withMessages(['status' => __('gaeb.billing.error.no_customer')]);
        }

        return DB::transaction(function () use ($callOff, $boq, $customer, $actor): Invoice {
            BoqCallOff::query()->whereKey($callOff->id)->lockForUpdate()->first();
            $draft = $this->invoices->emptyDraft($customer, $boq->project);
            $draft->forceFill(['bill_of_quantity_id' => $boq->id])->save();

            $position = 0;
            $serviceDate = ($callOff->due_on ?? $callOff->ordered_on)?->toDateString();
            foreach ($callOff->items()->with('item')->get() as $line) {
                $item = $line->item;
                if (! $item instanceof BoqItem) {
                    continue;
                }
                $draft->items()->create([
                    'organization_id' => $draft->organization_id,
                    'service_date' => $serviceDate,
                    'description' => trim($item->reference_no . ' ' . ($item->short_text ?? '')),
                    'quantity' => $line->quantity,
                    'unit' => (string) $item->unit,
                    'unit_price' => $item->unit_price?->getAmount() ?? '0',
                    'discount_percent' => $item->discount_percent,
                    'position' => ++$position,
                ]);
            }
            $draft->notes = trim(((string) $draft->notes) . "\n" . __('gaeb.call_off.invoice_note', ['number' => $callOff->number, 'title' => $callOff->title, 'boq' => $boq->name]));
            $draft->load('items');
            $draft->recalculate();
            $draft->save();

            $callOff->update(['invoice_id' => $draft->id, 'updated_by' => $actor->id]);

            return $draft;
        });
    }
}
