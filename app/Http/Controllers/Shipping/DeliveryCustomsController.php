<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryCustomsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Shipping;

use App\Enums\Shipping\ShipmentExportReason;
use App\Http\Controllers\Controller;
use App\Models\Inventory\StockDelivery;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Services\Shipping\CustomsInvoicePdfRenderer;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Zollpapiere zur Auslieferung (Feature 059, MVP-1007): Versandgrund wählen, PDF erzeugen. */
class DeliveryCustomsController extends Controller {
    public function __construct(private readonly CustomsInvoicePdfRenderer $renderer) {}

    public function form(ManufacturingOrder $order, StockDelivery $delivery): View {
        Gate::authorize('update', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);

        return view('manufacturing._customs_dialog', [
            'order' => $order,
            'delivery' => $delivery,
            'required' => $this->renderer->requiresCustoms($delivery),
            'missing' => $this->renderer->missingFields($delivery),
            'reasons' => ShipmentExportReason::cases(),
        ]);
    }

    public function pdf(Request $request, ManufacturingOrder $order, StockDelivery $delivery): Response {
        Gate::authorize('update', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);
        $data = $request->validate(['export_reason' => ['required', Rule::enum(ShipmentExportReason::class)]]);
        $reason = ShipmentExportReason::from((string) $data['export_reason']);
        $pdf = $this->renderer->render($delivery, $reason);
        $delivery->update(['export_reason' => $reason]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->renderer->number($delivery, $reason) . '.pdf"',
        ]);
    }
}
