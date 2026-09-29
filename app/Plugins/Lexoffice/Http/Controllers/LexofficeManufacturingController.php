<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeManufacturingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockDelivery;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Plugins\Lexoffice\{LexofficeDeliveryNoteService, LexofficeOrderConfirmationService, LexofficeQuotationService};
use App\Support\ErrorText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/** Belegübergabe aus der Fertigung (Feature 045/047); bis MVP-1040 im Kern-Controller. */
class LexofficeManufacturingController extends Controller {
    public function pushDeliveryNote(
        ManufacturingOrder $order,
        StockDelivery $delivery,
        LexofficeDeliveryNoteService $deliveryNotes,
    ): RedirectResponse {
        Gate::authorize('update', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);

        try {
            $deliveryNotes->push($delivery);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('manufacturing.order.flash.lexoffice_pushed'));
    }

    public function pushOrderConfirmation(
        ManufacturingOrder $order,
        LexofficeOrderConfirmationService $orderConfirmations,
    ): RedirectResponse {
        Gate::authorize('update', $order);

        try {
            $reference = $orderConfirmations->push($order);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('Auftragsbestätigung in Lexoffice angelegt (ID :id).', [
            'id' => $reference->external_id,
        ]));
    }

    public function pushQuotation(
        ManufacturingOrder $order,
        LexofficeQuotationService $quotations,
    ): RedirectResponse {
        Gate::authorize('update', $order);

        try {
            $reference = $quotations->push($order);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('Angebot in Lexoffice angelegt (ID :id).', [
            'id' => $reference->external_id,
        ]));
    }
}
