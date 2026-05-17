<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Fleet;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Vehicle
    {
        return DB::transaction(fn (): Vehicle => Vehicle::create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(Vehicle $vehicle, array $attributes): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $attributes): Vehicle {
            $vehicle->fill($attributes);
            $vehicle->save();

            return $vehicle->refresh();
        });
    }

    public function archive(Vehicle $vehicle): Vehicle
    {
        return DB::transaction(function () use ($vehicle): Vehicle {
            $vehicle->archived_at = Carbon::now();
            $vehicle->save();

            return $vehicle->refresh();
        });
    }

    public function restore(Vehicle $vehicle): Vehicle
    {
        return DB::transaction(function () use ($vehicle): Vehicle {
            $vehicle->archived_at = null;
            $vehicle->save();

            return $vehicle->refresh();
        });
    }
}
