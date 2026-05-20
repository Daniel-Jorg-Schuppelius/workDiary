<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Vehicle\VehicleOwnership;
use App\Enums\Vehicle\VehiclePropulsion;
use App\Enums\Vehicle\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory {
    protected $model = Vehicle::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'license_plate' => strtoupper(fake()->bothify('B-?? ###')),
            'label' => fake()->randomElement(['Sprinter', 'Caddy', 'eGolf', 'Corolla']),
            'vehicle_type' => VehicleType::Car->value,
            'propulsion' => VehiclePropulsion::Diesel->value,
            'ownership' => VehicleOwnership::Owned->value,
            'rental_provider' => null,
            'rental_start' => null,
            'rental_end' => null,
            'rental_cost_per_day' => null,
            'rental_included_km' => null,
            'rental_extra_cost_per_km' => null,
            'default_user_id' => null,
            'default_rate_per_km' => null,
            'tank_capacity_liters' => 60,
            'battery_capacity_kwh' => null,
            'wltp_consumption' => 6.5,
            'odometer_km' => fake()->numberBetween(10000, 200000),
            'notes' => null,
            'archived_at' => null,
        ];
    }

    public function electric(): self {
        return $this->state(fn() => [
            'propulsion' => VehiclePropulsion::Electric->value,
            'tank_capacity_liters' => null,
            'battery_capacity_kwh' => 75,
            'wltp_consumption' => 17.5,
        ]);
    }

    public function archived(): self {
        return $this->state(fn() => ['archived_at' => now()]);
    }

    public function rental(): self {
        return $this->state(fn() => [
            'ownership' => VehicleOwnership::Rental->value,
            'rental_provider' => 'Sixt',
            'rental_start' => now()->subDays(7)->toDateString(),
            'rental_end' => now()->addDays(7)->toDateString(),
            'rental_cost_per_day' => 49.90,
            'rental_included_km' => 1500,
            'rental_extra_cost_per_km' => 0.25,
        ]);
    }
}
