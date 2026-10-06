<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryStockStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Manufacturing;

/** Lagerstand einer Auslieferung (Feature 047, MVP-074), getrennt vom Fakturastand. */
enum DeliveryStockStatus: string {
    case Delivered = 'delivered';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Cancelled = 'cancelled';
}
