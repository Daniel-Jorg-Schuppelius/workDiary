<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentParcelController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Inventory\{StockDelivery, StockSerial};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Shipping\ShipmentParcel;
use App\Services\Shipping\ParcelService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/** Packstücke einer Auslieferung (Feature 059, MVP-900); Recht wie der Versandauftrag. */
class ShipmentParcelController extends Controller {
    public function __construct(private readonly ParcelService $parcels) {}

    public function create(ManufacturingOrder $order, StockDelivery $delivery): View {
        $this->authorizeDelivery($order, $delivery);

        return $this->dialog($order, $delivery, null);
    }

    public function edit(ManufacturingOrder $order, StockDelivery $delivery, ShipmentParcel $parcel): View {
        $this->authorizeDelivery($order, $delivery, $parcel);

        return $this->dialog($order, $delivery, $parcel->load('serials'));
    }

    public function store(Request $request, ManufacturingOrder $order, StockDelivery $delivery): RedirectResponse {
        $this->authorizeDelivery($order, $delivery);
        $this->parcels->save($delivery, null, $request->user() ?? abort(401), $this->validated($request));

        return redirect()->route('manufacturing-orders.show', $order)->with('success', __('shipping.parcel.saved'));
    }

    public function update(Request $request, ManufacturingOrder $order, StockDelivery $delivery, ShipmentParcel $parcel): RedirectResponse {
        $this->authorizeDelivery($order, $delivery, $parcel);
        $this->parcels->save($delivery, $parcel, $request->user() ?? abort(401), $this->validated($request));

        return redirect()->route('manufacturing-orders.show', $order)->with('success', __('shipping.parcel.saved'));
    }

    public function destroy(ManufacturingOrder $order, StockDelivery $delivery, ShipmentParcel $parcel): RedirectResponse {
        $this->authorizeDelivery($order, $delivery, $parcel);
        $this->parcels->delete($delivery, $parcel);

        return redirect()->route('manufacturing-orders.show', $order)->with('success', __('shipping.parcel.deleted'));
    }

    private function dialog(ManufacturingOrder $order, StockDelivery $delivery, ?ShipmentParcel $parcel): View {
        return view('shipping.parcels._form_dialog', [
            'order' => $order,
            'delivery' => $delivery,
            'parcel' => $parcel,
            'serials' => $this->parcels->assignableSerials($delivery, $parcel),
        ]);
    }

    /** @return array{weight_grams: int, length_cm: ?int, width_cm: ?int, height_cm: ?int, serials: list<int>} */
    private function validated(Request $request): array {
        $data = $request->validate([
            'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
            'length_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'width_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'height_cm' => ['nullable', 'integer', 'min:1', 'max:400'],
            'serials' => ['sometimes', 'array'],
            'serials.*' => ['string'],
        ]);

        return [
            'weight_grams' => (int) $data['weight_grams'],
            'length_cm' => isset($data['length_cm']) ? (int) $data['length_cm'] : null,
            'width_cm' => isset($data['width_cm']) ? (int) $data['width_cm'] : null,
            'height_cm' => isset($data['height_cm']) ? (int) $data['height_cm'] : null,
            'serials' => array_values(array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(StockSerial::class, $sqid), $data['serials'] ?? []))),
        ];
    }

    private function authorizeDelivery(ManufacturingOrder $order, StockDelivery $delivery, ?ShipmentParcel $parcel = null): void {
        Gate::authorize('update', $order);
        abort_unless($delivery->manufacturing_order_id === $order->id, 404);
        abort_unless($parcel === null || $parcel->stock_delivery_id === $delivery->id, 404);
    }
}
