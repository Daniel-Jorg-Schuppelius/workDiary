<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogVehicle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Travel;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum TravelLogVehicle: string implements HasLabel {
    use HasOptions;

    case Company = 'company';
    case Private_ = 'private';
    case Rental = 'rental';
    case PublicTransport = 'public_transport';
    case Bicycle = 'bicycle';
    case Foot = 'foot';
    case Other = 'other';

    public function label(): string {
        return (string) __('travel.vehicle.' . $this->value);
    }
}
