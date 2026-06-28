<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UglInvoiceReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procurement;

use App\Models\{PurchaseOrder, PurchaseOrderLine};
use ERechnungToolkit\Entities\{OrderLine, UglInvoice};

/**
 * Gleicht eine eingehende UGL-Rechnung (Großhandel → Handwerk) gegen die lokale
 * {@see PurchaseOrder} ab (Feature 050, Punkt 9). Reine Lese-/Vergleichsoperation
 * — die Rechnungshoheit bleibt beim führenden Programm
 * ({@see \App\Services\Procurement\PurchaseOrderExportService}); hier wird nur
 * geprüft, ob Positionen, Mengen und Beträge der Rechnung zur Bestellung passen.
 *
 * Zuordnung der Positionen über die Lieferanten-SKU (UGL SUPPLIER_PID ↔ Bestell-
 * zeile supplier_sku / Varianten-SKU). Beträge werden mit 1-Cent-Toleranz
 * verglichen.
 */
class UglInvoiceReconciler {
    private const TOLERANCE = 0.01;

    /**
     * @return array{
     *     invoice: UglInvoice,
     *     lines: list<array{sku: string, name: string, invoice_qty: float, invoice_net: float,
     *                        order_qty: float|null, order_net: float|null, status: string}>,
     *     missing: list<array{sku: string, name: string, order_qty: float, order_net: float}>,
     *     totals: array{invoice_net: float, order_net: float, matches: bool},
     *     ok: bool
     * }
     */
    public function reconcile(PurchaseOrder $order, UglInvoice $invoice): array {
        $order->loadMissing(['lines.article', 'lines.variant']);

        /** @var array<string, list<PurchaseOrderLine>> $poBySku */
        $poBySku = [];
        foreach ($order->lines as $line) {
            $sku = $this->lineSku($line);
            if ($sku !== '') {
                $poBySku[$sku][] = $line;
            }
        }

        $matchedIds = [];
        $lines = [];
        foreach ($invoice->getLines() as $invLine) {
            $sku = trim((string) $invLine->getSellersItemId());
            $poLine = null;
            if ($sku !== '' && ! empty($poBySku[$sku])) {
                $poLine = array_shift($poBySku[$sku]);
                $matchedIds[$poLine->id] = true;
            }
            $lines[] = $this->compareLine($invLine, $poLine);
        }

        $missing = [];
        $orderNet = 0.0;
        foreach ($order->lines as $line) {
            $net = $this->orderLineNet($line);
            $orderNet += $net;
            if (! isset($matchedIds[$line->id])) {
                $missing[] = [
                    'sku' => $this->lineSku($line),
                    'name' => $this->orderLineName($line),
                    'order_qty' => (float) $line->ordered_qty,
                    'order_net' => $net,
                ];
            }
        }
        $orderNet = round($orderNet, 2);

        $totalsMatch = abs($invoice->getNetTotal() - $orderNet) <= self::TOLERANCE;
        $allLinesMatch = array_reduce($lines, fn (bool $ok, array $l): bool => $ok && $l['status'] === 'match', true);

        return [
            'invoice' => $invoice,
            'lines' => $lines,
            'missing' => $missing,
            'totals' => [
                'invoice_net' => $invoice->getNetTotal(),
                'order_net' => $orderNet,
                'matches' => $totalsMatch,
            ],
            'ok' => $allLinesMatch && $missing === [] && $totalsMatch,
        ];
    }

    /**
     * @return array{sku: string, name: string, invoice_qty: float, invoice_net: float,
     *               order_qty: float|null, order_net: float|null, status: string}
     */
    private function compareLine(OrderLine $invLine, ?PurchaseOrderLine $poLine): array {
        $invoiceNet = round($invLine->getNetAmount(), 2);

        if ($poLine === null) {
            $status = 'invoice_only';
            $orderQty = null;
            $orderNet = null;
        } else {
            $orderQty = (float) $poLine->ordered_qty;
            $orderNet = $this->orderLineNet($poLine);
            $qtyOk = abs($invLine->getQuantity() - $orderQty) <= self::TOLERANCE;
            $netOk = abs($invoiceNet - $orderNet) <= self::TOLERANCE;
            $status = $qtyOk && $netOk ? 'match' : 'mismatch';
        }

        return [
            'sku' => trim((string) $invLine->getSellersItemId()),
            'name' => $invLine->getItemName(),
            'invoice_qty' => $invLine->getQuantity(),
            'invoice_net' => $invoiceNet,
            'order_qty' => $orderQty,
            'order_net' => $orderNet,
            'status' => $status,
        ];
    }

    private function lineSku(PurchaseOrderLine $line): string {
        return trim((string) $line->supplier_sku) ?: trim((string) $line->variant?->sku);
    }

    private function orderLineNet(PurchaseOrderLine $line): float {
        return round((float) $line->ordered_qty * (float) ($line->unit_price ?? 0), 2);
    }

    private function orderLineName(PurchaseOrderLine $line): string {
        $name = trim((string) $line->article->name);

        return $name !== '' ? $name : (trim((string) $line->description) ?: 'Artikel');
    }
}
