<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryShipmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Inventory\StockDelivery;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Shipping\{CarrierConnection, Shipment, ShipmentParcel};
use App\Services\Shipping\{ShipmentPackage, ShipmentRecipient, ShipmentRequest, ShipmentService};
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use RuntimeException;

/**
 * Versandauftrag zur Auslieferung eines Fertigungsauftrags (Feature 059,
 * MVP-128, Rang 20): Label idempotent beim gewählten Carrier abrufen.
 * Versandbezug ergibt sich transitiv über `stock_delivery_id` (Serien sind
 * beim Ausliefern bereits an den Empfänger gebunden).
 */
class DeliveryShipmentController extends Controller {
    public function store(Request $request, ManufacturingOrder $order, StockDelivery $delivery, ShipmentService $shipping): RedirectResponse {
        Gate::authorize('update', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);

        $customer = $delivery->customer;
        if (! $customer instanceof Customer) {
            return back()->with('error', __('shipping.flash.no_recipient'));
        }
        if ($delivery->shipment()->exists()) {
            return back()->with('error', __('shipping.flash.already_created'));
        }

        $data = $request->validate([
            'carrier' => ['required', 'string', 'max:24'],
            // Mit erfassten Packstücken (MVP-900) kommen Gewicht und Maße von dort.
            'weight_grams' => [$delivery->parcels()->exists() ? 'nullable' : 'required', 'integer', 'min:1', 'max:1000000'],
            // Optionale Packstück-Maße (cm); UPS/FedEx nur wirksam, wenn alle drei gesetzt.
            'length_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'width_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'height_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
        ]);

        $hasConnection = CarrierConnection::query()
            ->where('carrier', $data['carrier'])
            ->where('active', true)
            ->exists();
        if (! $hasConnection) {
            return back()->with('error', __('shipping.flash.no_connection'));
        }

        $shipment = Shipment::query()->create([
            'organization_id' => $order->organization_id,
            'stock_delivery_id' => $delivery->id,
            'carrier' => (string) $data['carrier'],
            'status' => \App\Enums\Shipping\ShipmentStatus::Draft->value,
            'created_by' => Auth::id() !== null ? (int) Auth::id() : null,
        ]);

        $recipient = new ShipmentRecipient(
            name: (string) ($customer->displayLabel()),
            street: (string) $customer->address_street,
            zip: (string) $customer->address_zip,
            city: (string) $customer->address_city,
            country: (string) ($customer->country ?: 'DE'),
            contactName: $customer->company ? $customer->name : null,
            email: $customer->email,
            phone: $customer->phone,
        );

        $packages = array_values($delivery->parcels->map(static fn (ShipmentParcel $p): ShipmentPackage => new ShipmentPackage($p->weight_grams, $p->length_cm, $p->width_cm, $p->height_cm))->all());
        if ($packages === []) {
            $packages = [new ShipmentPackage(
                (int) $data['weight_grams'],
                isset($data['length_cm']) ? (int) $data['length_cm'] : null,
                isset($data['width_cm']) ? (int) $data['width_cm'] : null,
                isset($data['height_cm']) ? (int) $data['height_cm'] : null,
            )];
        }
        $shipmentRequest = new ShipmentRequest($recipient, $packages, 'MO-' . $order->id . '/D-' . $delivery->id);

        try {
            $shipping->createLabel($shipment, $shipmentRequest);
        } catch (RuntimeException $e) {
            $shipment->delete(); // Entwurf verwerfen, wenn der Carrier ablehnt

            return back()->with('error', __('shipping.flash.label_failed', ['reason' => ErrorText::for($e)]));
        }

        return back()->with('success', __('shipping.flash.label_created'));
    }
}
