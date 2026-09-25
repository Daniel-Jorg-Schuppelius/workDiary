<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParcelService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\Inventory\{StockDelivery, StockSerial};
use App\Models\Platform\User;
use App\Models\Shipping\ShipmentParcel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Packstücke einer Auslieferung (MVP-900). Seriennummern stammen nur aus
 * dieser Auslieferung und liegen in genau einem Packstück; nach dem
 * Versandauftrag sind die Packstücke fest.
 */
final class ParcelService {
    /** @param  array{weight_grams: int, length_cm?: ?int, width_cm?: ?int, height_cm?: ?int, serials?: list<int>}  $data */
    public function save(StockDelivery $delivery, ?ShipmentParcel $parcel, User $actor, array $data): ShipmentParcel {
        $this->assertEditable($delivery);

        return DB::transaction(function () use ($delivery, $parcel, $actor, $data): ShipmentParcel {
            $attributes = [
                'weight_grams' => $data['weight_grams'],
                'length_cm' => $data['length_cm'] ?? null,
                'width_cm' => $data['width_cm'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'updated_by' => $actor->id,
            ];
            if ($parcel === null) {
                $position = (int) ShipmentParcel::query()->where('stock_delivery_id', $delivery->id)->lockForUpdate()->max('position') + 1;
                /** @var ShipmentParcel $parcel */
                $parcel = ShipmentParcel::query()->create($attributes + [
                    'organization_id' => $delivery->organization_id,
                    'stock_delivery_id' => $delivery->id,
                    'position' => $position,
                    'created_by' => $actor->id,
                ]);
            } else {
                $parcel->update($attributes);
            }
            $this->assignSerials($delivery, $parcel, $data['serials'] ?? []);

            return $parcel;
        });
    }

    public function delete(StockDelivery $delivery, ShipmentParcel $parcel): void {
        $this->assertEditable($delivery);
        DB::transaction(function () use ($delivery, $parcel): void {
            $parcel->delete();
            // Lückenlose Nummerierung „x von n“.
            $position = 1;
            foreach (ShipmentParcel::query()->where('stock_delivery_id', $delivery->id)->orderBy('position')->get() as $rest) {
                $rest->forceFill(['position' => $position++])->save();
            }
        });
    }

    /** @return Collection<int, StockSerial> Seriennummern der Auslieferung ohne Packstück (außer im angegebenen) */
    public function assignableSerials(StockDelivery $delivery, ?ShipmentParcel $parcel = null): Collection {
        return StockSerial::query()
            ->where('stock_delivery_id', $delivery->id)
            ->whereNotIn('id', DB::table('shipment_parcel_serials')
                ->when($parcel !== null, fn ($q) => $q->where('shipment_parcel_id', '!=', $parcel?->id))
                ->select('stock_serial_id'))
            ->orderBy('serial_no')
            ->get();
    }

    /** @param  list<int>  $serialIds */
    private function assignSerials(StockDelivery $delivery, ShipmentParcel $parcel, array $serialIds): void {
        $serialIds = array_values(array_unique($serialIds));
        $allowed = $this->assignableSerials($delivery, $parcel)->pluck('id')->all();
        $foreign = array_diff($serialIds, $allowed);
        if ($foreign !== []) {
            throw ValidationException::withMessages(['serials' => __('shipping.parcel.serial_not_allowed')]);
        }
        $parcel->serials()->sync(array_fill_keys($serialIds, ['organization_id' => $parcel->organization_id]));
    }

    private function assertEditable(StockDelivery $delivery): void {
        if ($delivery->shipment()->exists()) {
            throw ValidationException::withMessages(['weight_grams' => __('shipping.parcel.locked')]);
        }
    }
}
