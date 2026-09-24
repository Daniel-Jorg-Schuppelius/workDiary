<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryInvoicingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Events\Invoicing\DeliveryInvoiced;
use App\Models\Inventory\StockDelivery;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fertigungsauslieferungen lokal abrechnen (Feature 160, MVP-858): eine
 * erfolgte Auslieferung mit lokalem Fakturaziel wird vollständig in genau
 * einen aktiven Rechnungsposten übernommen. Die aktive Reservierung steht an
 * der Auslieferung (`invoice_item_id`, Unique), die Herkunft bleibt am Posten
 * (`stock_delivery_id`) auch nach Freigabe oder Storno erhalten. Lagerbestand
 * wird hier nie gebucht.
 */
class DeliveryInvoicingService {
    public const TARGET_LOCAL = 'workdiary';

    /**
     * Abrechenbare und blockierte Auslieferungen des Rechnungskunden im Zeitraum
     * (gelieferte, nicht reservierte, lokal zu fakturierende Auslieferungen);
     * `block_reason` nennt, warum eine Zeile nicht übernommen werden kann.
     *
     * @return Collection<int, array{delivery: StockDelivery, block_reason: string|null, reserved_by: Invoice|null}>
     */
    public function candidatesFor(Invoice $draft, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection {
        $toExclusive = $to !== null ? \Carbon\CarbonImmutable::instance($to)->addDay()->startOfDay() : null;
        $rows = StockDelivery::query()
            ->where('organization_id', $draft->organization_id)
            ->where('customer_id', $draft->customer_id)
            ->where('facturation_target', self::TARGET_LOCAL)
            ->where('stock_status', 'delivered')
            ->whereIn('facturation_status', [DeliveryFacturationStatus::Pending->value, DeliveryFacturationStatus::Failed->value])
            ->when($from !== null, fn($q) => $q->where('delivered_at', '>=', $from))
            ->when($toExclusive !== null, fn($q) => $q->where('delivered_at', '<', $toExclusive))
            ->with(['order', 'variant.article', 'invoiceItem.invoice'])
            ->orderByDesc('delivered_at')
            ->get();

        return $rows->map(function (StockDelivery $delivery) use ($draft): array {
            $reservedBy = $delivery->invoiceItem?->invoice;

            return [
                'delivery' => $delivery,
                'block_reason' => $this->blockReason($draft, $delivery),
                'reserved_by' => $reservedBy,
            ];
        })->values();
    }

    /**
     * Auslieferungen als Posten übernehmen — alles oder nichts: Kunde, Mandant,
     * Projekt, Währung und Ziel müssen passen; die Zeilensperre plus Unique
     * verhindern eine zweite aktive Zuordnung auch bei parallelen Anfragen.
     *
     * @param  list<int>  $deliveryIds
     * @return EloquentCollection<int, InvoiceItem>
     */
    public function attach(Invoice $draft, array $deliveryIds, ?User $actor = null): EloquentCollection {
        return DB::transaction(function () use ($draft, $deliveryIds): EloquentCollection {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== Invoice::STATUS_DRAFT || ! in_array($invoice->type, [Invoice::TYPE_INVOICE, Invoice::TYPE_PARTIAL, Invoice::TYPE_FINAL], true)) {
                throw ValidationException::withMessages(['delivery_ids' => __('invoicing.free.error.draft_only')]);
            }
            $ids = array_values(array_unique(array_map('intval', $deliveryIds)));
            if ($ids === []) {
                throw ValidationException::withMessages(['delivery_ids' => __('invoicing.free.error.delivery_required')]);
            }
            $position = (int) $invoice->items()->max('position');
            $items = new EloquentCollection();
            foreach ($ids as $id) {
                /** @var StockDelivery|null $delivery */
                $delivery = StockDelivery::query()->whereKey($id)->where('organization_id', $invoice->organization_id)->lockForUpdate()->first();
                if ($delivery === null) {
                    throw ValidationException::withMessages(['delivery_ids' => __('invoicing.free.error.delivery_foreign')]);
                }
                $reason = $this->blockReason($invoice, $delivery);
                if ($reason !== null) {
                    throw ValidationException::withMessages(['delivery_ids' => $reason]);
                }
                $price = $delivery->unit_price_snapshot;
                if ($price === null) {
                    throw ValidationException::withMessages(['delivery_ids' => __('invoicing.free.error.delivery_without_price', ['name' => (string) $delivery->name_snapshot])]);
                }
                $variant = $delivery->variant;
                $item = $invoice->items()->create([
                    'organization_id' => $invoice->organization_id,
                    'article_id' => $variant?->article_id,
                    'article_variant_id' => $delivery->article_variant_id,
                    'article_number_snapshot' => $delivery->sku_snapshot,
                    'stock_delivery_id' => $delivery->id,
                    'service_date' => $delivery->delivered_at->toDateString(),
                    'description' => $this->description($delivery),
                    'quantity' => (string) ($delivery->quantity?->getNumericValue() ?? '0'),
                    'unit' => (string) $delivery->unit,
                    'unit_price' => $price->getAmount(),
                    'position' => ++$position,
                ]);
                try {
                    $delivery->forceFill(['invoice_item_id' => $item->id])->saveQuietly();
                } catch (QueryException) {
                    // Unique auf invoice_item_id: eine parallele Übernahme war schneller.
                    throw ValidationException::withMessages(['delivery_ids' => __('invoicing.free.error.delivery_reserved', ['name' => (string) $delivery->name_snapshot, 'number' => '?'])]);
                }
                $invoice->audit('invoice.delivery_attached', ['delivery_id' => $delivery->id, 'item_id' => $item->id, 'quantity' => (string) $item->quantity]);
                $items->push($item);
            }
            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $items;
        });
    }

    /** Reservierung lösen (Posten entfernt, Entwurf verworfen, Vollstorno) — die Herkunft am Posten bleibt. */
    public function release(InvoiceItem $item): int {
        return StockDelivery::query()
            ->where('invoice_item_id', $item->id)
            ->update(['invoice_item_id' => null, 'facturation_status' => DeliveryFacturationStatus::Pending->value]);
    }

    /** Ausstellung: reservierte Quellen der Rechnung gelten als abgerechnet. */
    public function markInvoiced(Invoice $invoice): int {
        $count = 0;
        $itemIds = $invoice->items()->whereNotNull('stock_delivery_id')->pluck('id');
        if ($itemIds->isEmpty()) {
            return 0;
        }
        foreach (StockDelivery::query()->whereIn('invoice_item_id', $itemIds)->get() as $delivery) {
            DeliveryInvoiced::dispatch($delivery, $invoice);
            $count++;
        }

        return $count;
    }

    /**
     * Vollstorno: die Quellen der stornierten Rechnung werden zur erneuten
     * Abrechnung frei; Teilgutschriften rufen das bewusst nicht auf.
     */
    public function releaseForCancellation(Invoice $original): int {
        $count = 0;
        foreach ($original->items()->whereNotNull('stock_delivery_id')->get() as $item) {
            $count += $this->release($item);
        }

        return $count;
    }

    private function blockReason(Invoice $invoice, StockDelivery $delivery): ?string {
        if ($delivery->customer_id !== $invoice->customer_id) {
            return (string) __('invoicing.free.error.delivery_customer');
        }
        if ($delivery->facturation_target !== self::TARGET_LOCAL) {
            return (string) __('invoicing.free.error.delivery_external');
        }
        if ($delivery->stock_status !== 'delivered') {
            return (string) __('invoicing.free.error.delivery_not_delivered');
        }
        if ($delivery->facturation_status === DeliveryFacturationStatus::Invoiced) {
            return (string) __('invoicing.free.error.delivery_invoiced');
        }
        if ($delivery->invoice_item_id !== null) {
            $reservedBy = $delivery->invoiceItem?->invoice;

            return (string) __('invoicing.free.error.delivery_reserved', ['name' => (string) $delivery->name_snapshot, 'number' => $reservedBy !== null ? (string) $reservedBy->number : '?']);
        }
        if ($delivery->currency !== $invoice->currency) {
            return (string) __('invoicing.free.error.delivery_currency', ['currency' => $delivery->currency->value, 'invoice' => $invoice->currency->value]);
        }
        $orderProjectId = $delivery->order?->project_id;
        if ($invoice->project_id !== null && $orderProjectId !== null && (int) $orderProjectId !== (int) $invoice->project_id) {
            return (string) __('invoicing.free.error.delivery_project');
        }

        return null;
    }

    private function description(StockDelivery $delivery): string {
        $parts = [(string) $delivery->name_snapshot];
        if ($delivery->sku_snapshot !== null && $delivery->sku_snapshot !== '') {
            $parts[] = '(' . $delivery->sku_snapshot . ')';
        }
        $orderNumber = $delivery->order?->number;
        if ($orderNumber !== null && $orderNumber !== '') {
            $parts[] = '– ' . (string) __('invoicing.free.label.from_order', ['number' => (string) $orderNumber]);
        }

        return mb_substr(implode(' ', $parts), 0, 1000);
    }
}
