<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderResponseImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Procurement\{PurchaseOrder, PurchaseOrderLine};
use App\Services\Procurement\Concerns\ResolvesPurchaseOrderLines;
use App\Services\Procurement\Contracts\PurchaseOrderGaebResponseReader;
use App\Services\Procurement\Dto\{OrderResponseData, OrderResponseLine};
use CommonToolkit\Helper\Data\NumberHelper;
use ERechnungToolkit\Parsers\OpenTransOrderResponseParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Antwort des Lieferanten auf eine Bestellung (MVP-964 openTRANS
 * ORDERRESPONSE, MVP-1300 GAEB X97/X94).
 *
 * Eine Auftragsbestätigung legt bestätigte Menge, Preis und Termin je
 * Bestellzeile ab, dazu die Auftragsnummer des Lieferanten. Abweichungen
 * werden gemeldet, nicht übernommen — Bestellmenge und -preis bleiben.
 * Ein Preisangebot (X94) beantwortet die Preisanfrage eines Entwurfs: seine
 * Preise werden die Einzelpreise der Bestellung.
 */
class OrderResponseImportService {
    use ResolvesPurchaseOrderLines;

    public function __construct(private readonly PurchaseOrderGaebResponseReader $gaeb) {}

    /**
     * @return array{order: PurchaseOrder, kind: string, confirmed: int, deviations: list<array{line: PurchaseOrderLine, kinds: list<string>}>}
     */
    public function import(string $xml, PurchaseOrder $order): array {
        $response = $this->gaeb->read($xml) ?? $this->openTrans($xml);
        if (trim($response->orderNumber) !== trim((string) $order->number)) {
            throw new RuntimeException((string) __('procurement.confirmation.error.other_order', ['number' => $response->orderNumber]));
        }

        $order->loadMissing('lines');
        /** @var Collection<int, PurchaseOrderLine> $orderedLines */
        $orderedLines = $order->lines->sortBy('id')->values();

        return $response->kind === OrderResponseData::OFFER
            ? $this->applyOffer($response, $order, $orderedLines)
            : $this->confirm($response, $order, $orderedLines);
    }

    private function openTrans(string $xml): OrderResponseData {
        $response = (new OpenTransOrderResponseParser)->parse($xml);
        $lines = [];
        foreach ($response->getLines() as $line) {
            $lines[] = new OrderResponseLine(
                lineId: $line->getLineId(),
                sellerItemId: $line->getSellersItemId(),
                quantity: $line->getQuantity(),
                unitPrice: $line->getUnitPrice()?->getAmount(),
                deliveryDate: $line->getDeliveryDate()?->format('Y-m-d'),
            );
        }

        return new OrderResponseData(OrderResponseData::CONFIRMATION, $response->getOrderId(), $response->getSupplierOrderId(), $lines);
    }

    /**
     * @param  Collection<int, PurchaseOrderLine>  $orderedLines
     * @return array{order: PurchaseOrder, kind: string, confirmed: int, deviations: list<array{line: PurchaseOrderLine, kinds: list<string>}>}
     */
    private function confirm(OrderResponseData $response, PurchaseOrder $order, Collection $orderedLines): array {
        $confirmed = 0;
        $deviations = [];

        DB::transaction(function () use ($response, $order, $orderedLines, &$confirmed, &$deviations): void {
            foreach ($response->lines as $responseLine) {
                $line = $this->resolveOrderLine($orderedLines, $responseLine->lineId, $responseLine->sellerItemId);
                if ($line === null) {
                    continue;
                }
                $quantity = $responseLine->quantity ?? 0.0;
                $qty = NumberHelper::toUSFormat($quantity, 4);
                $price = $responseLine->unitPrice;
                $delivery = $responseLine->deliveryDate;
                $line->forceFill(['confirmed_qty' => $qty, 'confirmed_unit_price' => $price, 'confirmed_delivery_on' => $delivery])->save();
                $confirmed++;

                $kinds = [];
                $ordered = $line->ordered_qty?->getValue()->toFloat() ?? 0.0;
                if (abs($ordered - $quantity) > 0.00005) {
                    $kinds[] = 'quantity';
                }
                $orderedPrice = $line->unit_price?->getAmount();
                if ($price !== null && $orderedPrice !== null && NumberHelper::comparePrecise($price, $orderedPrice, 2) !== 0) {
                    $kinds[] = 'price';
                }
                if ($delivery !== null && $order->expected_at !== null && $delivery !== $order->expected_at->format('Y-m-d')) {
                    $kinds[] = 'date';
                }
                if ($kinds !== []) {
                    $deviations[] = ['line' => $line, 'kinds' => $kinds];
                }
            }
            if ($confirmed === 0) {
                throw new RuntimeException((string) __('procurement.confirmation.error.no_lines'));
            }
            $order->forceFill(['supplier_confirmed_at' => now(), 'supplier_order_ref' => $response->supplierRef])->save();
            $order->audit('purchase_order.confirmed', ['lines' => $confirmed, 'deviations' => count($deviations), 'supplier_order_ref' => $response->supplierRef]);
        });

        return ['order' => $order, 'kind' => OrderResponseData::CONFIRMATION, 'confirmed' => $confirmed, 'deviations' => $deviations];
    }

    /**
     * Preisangebot auf die Preisanfrage (X94, MVP-1300) — nur solange die
     * Bestellung Entwurf ist; danach gilt der bestellte Preis.
     *
     * @param  Collection<int, PurchaseOrderLine>  $orderedLines
     * @return array{order: PurchaseOrder, kind: string, confirmed: int, deviations: list<array{line: PurchaseOrderLine, kinds: list<string>}>}
     */
    private function applyOffer(OrderResponseData $response, PurchaseOrder $order, Collection $orderedLines): array {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw new RuntimeException((string) __('procurement.offer.error_not_draft'));
        }

        $priced = 0;
        DB::transaction(function () use ($response, $order, $orderedLines, &$priced): void {
            foreach ($response->lines as $responseLine) {
                $line = $this->resolveOrderLine($orderedLines, $responseLine->lineId, $responseLine->sellerItemId);
                if ($line === null || $responseLine->unitPrice === null) {
                    continue;
                }
                $line->update(['unit_price' => $responseLine->unitPrice]);
                $priced++;
            }
            if ($priced === 0) {
                throw new RuntimeException((string) __('procurement.confirmation.error.no_lines'));
            }
            $order->audit('purchase_order.offerApplied', ['lines' => $priced, 'offer_ref' => $response->supplierRef]);
        });

        return ['order' => $order, 'kind' => OrderResponseData::OFFER, 'confirmed' => $priced, 'deviations' => []];
    }
}
