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

use App\Models\Procurement\{PurchaseOrder, PurchaseOrderLine};
use App\Services\Procurement\Concerns\ResolvesPurchaseOrderLines;
use CommonToolkit\Helper\Data\NumberHelper;
use ERechnungToolkit\Parsers\OpenTransOrderResponseParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Auftragsbestätigung des Lieferanten (MVP-964, openTRANS ORDERRESPONSE):
 * bestätigte Menge, Preis und Termin je Bestellzeile, dazu die
 * Auftragsnummer des Lieferanten. Abweichungen werden gemeldet, nicht
 * übernommen — Bestellmenge und -preis bleiben unverändert.
 */
class OrderResponseImportService {
    use ResolvesPurchaseOrderLines;

    /**
     * @return array{order: PurchaseOrder, confirmed: int, deviations: list<array{line: PurchaseOrderLine, kinds: list<string>}>}
     */
    public function import(string $xml, PurchaseOrder $order): array {
        $response = (new OpenTransOrderResponseParser)->parse($xml);
        if (trim($response->getOrderId()) !== trim((string) $order->number)) {
            throw new RuntimeException((string) __('procurement.confirmation.error.other_order', ['number' => $response->getOrderId()]));
        }

        $order->loadMissing('lines');
        /** @var Collection<int, PurchaseOrderLine> $orderedLines */
        $orderedLines = $order->lines->sortBy('id')->values();
        $confirmed = 0;
        $deviations = [];

        DB::transaction(function () use ($response, $order, $orderedLines, &$confirmed, &$deviations): void {
            foreach ($response->getLines() as $responseLine) {
                $line = $this->resolveOrderLine($orderedLines, $responseLine->getLineId(), $responseLine->getSellersItemId());
                if ($line === null) {
                    continue;
                }
                $qty = NumberHelper::toUSFormat($responseLine->getQuantity(), 4);
                $price = $responseLine->getUnitPrice()?->getAmount();
                $delivery = $responseLine->getDeliveryDate()?->format('Y-m-d');
                $line->forceFill(['confirmed_qty' => $qty, 'confirmed_unit_price' => $price, 'confirmed_delivery_on' => $delivery])->save();
                $confirmed++;

                $kinds = [];
                $ordered = $line->ordered_qty?->getValue()->toFloat() ?? 0.0;
                if (abs($ordered - $responseLine->getQuantity()) > 0.00005) {
                    $kinds[] = 'quantity';
                }
                $orderedPrice = $line->unit_price?->getAmount();
                if ($price !== null && $orderedPrice !== null && bccomp($price, $orderedPrice, 2) !== 0) {
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
            $order->forceFill(['supplier_confirmed_at' => now(), 'supplier_order_ref' => $response->getSupplierOrderId()])->save();
            $order->audit('purchase_order.confirmed', ['lines' => $confirmed, 'deviations' => count($deviations), 'supplier_order_ref' => $response->getSupplierOrderId()]);
        });

        return ['order' => $order, 'confirmed' => $confirmed, 'deviations' => $deviations];
    }
}
