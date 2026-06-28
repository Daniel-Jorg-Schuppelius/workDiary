<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Organization, PurchaseOrder};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Rendert eine Bestellung als menschenlesbares PDF (Feature 048, E4) — für
 * Lieferanten ohne elektronische Beschaffung (XBestellung/Order-X). Reiner
 * Bestell-Beleg mit Positionen, Einzel- und Summenpreis.
 */
class PurchaseOrderPdfRenderer {
    public const SCALE = 2;

    public function render(PurchaseOrder $order): string {
        $order->loadMissing(['supplier', 'warehouse', 'lines.article']);
        $organization = Organization::query()->withoutGlobalScopes()->find($order->organization_id);

        $total = '0';
        foreach ($order->lines as $line) {
            $total = bcadd($total, bcmul((string) $line->ordered_qty, (string) ($line->unit_price ?? '0'), self::SCALE), self::SCALE);
        }

        $html = View::make('pdf.purchase-order', [
            'order' => $order,
            'organization' => $organization,
            'total' => $total,
        ])->render();

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return (string) $pdf->output();
    }

    /** Dateiname-tauglicher Bezeichner aus der Bestellnummer. */
    public function filename(PurchaseOrder $order): string {
        return 'Bestellung_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $order->number);
    }
}
