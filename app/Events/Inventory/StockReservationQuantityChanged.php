<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockReservationQuantityChanged.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Models\Inventory\StockReservation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Reservierte Menge geändert — Teilfreigabe oder Umverteilung (MVP-1626). Die
 * Fertigung führt die reservierte Menge ihrer Materialposition nach. Synchron
 * in der Transaktion: beide Zahlen gehören zusammen.
 *
 * @param numeric-string $delta Änderung in Basiseinheit, negativ bei Abgabe
 */
final class StockReservationQuantityChanged {
    use Dispatchable;

    public function __construct(public readonly StockReservation $reservation, public readonly string $delta) {}
}
