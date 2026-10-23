<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Billing\{DeliveryNoteStatus, DocumentLineKind};
use App\Enums\Numbering\NumberScope;
use App\Http\Controllers\Controller;
use App\Models\Billing\{DeliveryNote, DeliveryNoteItem};
use App\Models\Inventory\StockDelivery;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Sales\OrderConfirmation;
use App\Services\Billing\{DeliveryNotePdfRenderer, DeliveryNoteService};
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Lieferschein als eigener Beleg (Phase 145, MVP-1163). */
class DeliveryNoteController extends Controller {
    public function __construct(private readonly DeliveryNoteService $notes) {}

    /** Aus der bestätigten AB anlegen; ein offener Entwurf wird geöffnet. */
    public function store(OrderConfirmation $orderConfirmation): RedirectResponse {
        Gate::authorize('view', $orderConfirmation);
        Gate::authorize('viewAny', DeliveryNote::class);
        try {
            $note = $this->notes->fromOrderConfirmation($orderConfirmation, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('delivery-notes.show', $note);
    }

    public function show(DeliveryNote $deliveryNote): View {
        Gate::authorize('view', $deliveryNote);
        $deliveryNote->load(['items.stockDelivery', 'customer', 'orderConfirmation', 'manufacturingOrder']);
        $authority = app(NumberAuthority::class);

        return view('delivery-notes.show', [
            'note' => $deliveryNote,
            // Entwurfsnummer: Das Programm vergibt die Nummer beim Ausliefern (MVP-1164).
            'numberIssuer' => NumberSequenceService::isDraft($deliveryNote->number)
                ? $authority->sourceLabel($authority->sourceFor((int) $deliveryNote->organization_id, NumberScope::DeliveryNote))
                : null,
        ]);
    }

    public function edit(DeliveryNote $deliveryNote): View {
        Gate::authorize('update', $deliveryNote);
        abort_if($deliveryNote->status !== DeliveryNoteStatus::Draft || $deliveryNote->hasStockDeliveries(), 409, (string) __('billing.delivery_note.error.not_editable'));

        return view('delivery-notes._edit_dialog', [
            'note' => $deliveryNote,
            'lines' => $deliveryNote->items->filter(fn (DeliveryNoteItem $item): bool => $item->line_kind === DocumentLineKind::Item)->values(),
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote): RedirectResponse {
        Gate::authorize('update', $deliveryNote);
        $data = $request->validate([
            'quantities' => ['array'],
            'quantities.*' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'delivered_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $quantities = [];
        foreach ((array) ($data['quantities'] ?? []) as $sqid => $quantity) {
            $item = $deliveryNote->items->first(fn (DeliveryNoteItem $item): bool => $item->sqid === (string) $sqid);
            if ($item instanceof DeliveryNoteItem) {
                $quantities[$item->id] = (string) $quantity;
            }
        }
        try {
            $this->notes->updateDraft($deliveryNote, $quantities, (string) $data['delivered_on'], $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('status', __('billing.delivery_note.flash.updated'));
    }

    public function deliver(DeliveryNote $deliveryNote): RedirectResponse {
        Gate::authorize('deliver', $deliveryNote);
        try {
            $this->notes->deliver($deliveryNote, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('delivery-notes.show', $deliveryNote)
            ->with('status', __('billing.delivery_note.flash.delivered', ['number' => $deliveryNote->refresh()->number]));
    }

    public function cancel(DeliveryNote $deliveryNote): RedirectResponse {
        Gate::authorize('cancel', $deliveryNote);
        try {
            $this->notes->cancel($deliveryNote, $this->authUser());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('delivery-notes.show', $deliveryNote)->with('status', __('billing.delivery_note.flash.cancelled'));
    }

    public function pdf(DeliveryNote $deliveryNote, DeliveryNotePdfRenderer $renderer): Response {
        Gate::authorize('view', $deliveryNote);
        abort_if($deliveryNote->status === DeliveryNoteStatus::Draft, 409, (string) __('billing.delivery_note.error.not_delivered'));

        return response($renderer->render($deliveryNote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $renderer->filename($deliveryNote) . '"',
        ]);
    }

    /** Bisheriger Weg aus dem Fertigungsauftrag (MVP-074) — führt zum Beleg der Auslieferung. */
    public function stockDeliveryPdf(ManufacturingOrder $order, StockDelivery $delivery): RedirectResponse {
        Gate::authorize('view', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);

        return redirect()->route('delivery-notes.pdf', $this->notes->forStockDelivery($delivery));
    }
}
