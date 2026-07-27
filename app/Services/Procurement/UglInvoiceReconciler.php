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
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
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
    /** Mengen bleiben skalar — Beträge vergleicht Money exakt. */
    private const QUANTITY_TOLERANCE = 0.01;

    /**
     * @return array{
     *     invoice: UglInvoice,
     *     lines: list<array{sku: string, name: string, invoice_qty: float, invoice_net: Money,
     *                        order_qty: float|null, order_net: Money|null, status: string}>,
     *     missing: list<array{sku: string, name: string, order_qty: float, order_net: Money}>,
     *     totals: array{invoice_net: Money, order_net: Money, matches: bool},
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
        $currency = $invoice->getCurrency();
        $orderNet = Money::zero($currency);
        foreach ($order->lines as $line) {
            $net = $this->orderLineNet($line, $currency);
            $orderNet = $orderNet->plus($net);
            if (! isset($matchedIds[$line->id])) {
                $missing[] = [
                    'sku' => $this->lineSku($line),
                    'name' => $this->orderLineName($line),
                    'order_qty' => ($line->ordered_qty?->getValue()->toFloat() ?? 0.0),
                    'order_net' => $net,
                ];
            }
        }
        // Money rechnet exakt — Netto-Summen müssen übereinstimmen, keine Toleranz.
        $totalsMatch = $invoice->getNetTotal()->equals($orderNet);
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
     * @return array{sku: string, name: string, invoice_qty: float, invoice_net: Money,
     *               order_qty: float|null, order_net: Money|null, status: string}
     */
    private function compareLine(OrderLine $invLine, ?PurchaseOrderLine $poLine): array {
        $invoiceNet = $invLine->getNetAmount();

        if ($poLine === null) {
            $status = 'invoice_only';
            $orderQty = null;
            $orderNet = null;
        } else {
            $orderQty = ($poLine->ordered_qty?->getValue()->toFloat() ?? 0.0);
            $orderNet = $this->orderLineNet($poLine, $invoiceNet->getCurrency());
            $qtyOk = abs($invLine->getQuantity() - $orderQty) <= self::QUANTITY_TOLERANCE;
            $netOk = $invoiceNet->equals($orderNet);
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

    private function orderLineNet(PurchaseOrderLine $line, CurrencyCode $currency): Money {
        return ($line->unit_price ?? Money::zero($currency))->times($line->ordered_qty?->getValue()->toFloat() ?? 0.0);
    }

    private function orderLineName(PurchaseOrderLine $line): string {
        $name = trim((string) $line->article->name);

        return $name !== '' ? $name : (trim((string) $line->description) ?: 'Artikel');
    }
}
