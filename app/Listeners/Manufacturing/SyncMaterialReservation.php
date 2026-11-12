<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncMaterialReservation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Manufacturing;

use App\Events\Inventory\StockReservationQuantityChanged;
use App\Listeners\ModuleListener;
use App\Models\Manufacturing\ManufacturingOrderMaterial;
use CommonToolkit\ValueObjects\Decimal;

/**
 * Hält `reserved_qty` der Materialposition gleich der Reservierung (MVP-1626):
 * nach Teilfreigabe oder Umverteilung reserviert „Material reservieren“ die
 * Lücke wieder nach, statt sie für gedeckt zu halten.
 */
final class SyncMaterialReservation extends ModuleListener {
    private const int SCALE = 4;

    protected function module(): string {
        return 'manufacturing';
    }

    public function handle(StockReservationQuantityChanged $event): void {
        if (! $this->shouldHandle($event->reservation->organization_id)) {
            return;
        }
        $delta = Decimal::of($event->delta, self::SCALE);
        foreach (ManufacturingOrderMaterial::query()->where('stock_reservation_id', $event->reservation->id)->lockForUpdate()->get() as $material) {
            $next = Decimal::of((string) $material->reserved_qty, self::SCALE)->plus($delta);
            $material->reserved_qty = $next->isNegative() ? '0' : $next->getValue();
            $material->save();
        }
    }
}
