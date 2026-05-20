<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehiclePropulsion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Vehicle;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum VehiclePropulsion: string implements HasLabel {
    use HasOptions;

    case Diesel = 'diesel';
    case Petrol = 'petrol';
    case Gas = 'gas';
    case Hybrid = 'hybrid';
    case Electric = 'electric';
    case Muscle = 'muscle';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.vehicle.propulsion.' . $this->value);
    }

    /**
     * Expected energy unit when refilling/charging this propulsion type.
     */
    public function expectedEnergyUnit(): ?string {
        return match ($this) {
            self::Electric => 'kwh',
            self::Muscle, self::Other => null,
            default => 'liter',
        };
    }
}
