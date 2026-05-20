<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Vehicle;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum VehicleType: string implements HasLabel {
    use HasOptions;

    case Car = 'car';
    case Van = 'van';
    case Truck = 'truck';
    case Bicycle = 'bicycle';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.vehicle.type.' . $this->value);
    }
}
