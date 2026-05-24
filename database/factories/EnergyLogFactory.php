<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnergyLogFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{EnergyLog, User, Vehicle};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EnergyLog>
 */
class EnergyLogFactory extends Factory {
    protected $model = EnergyLog::class;

    public function definition(): array {
        $start = Carbon::instance(fake()->dateTimeBetween('-30 days', 'now'));

        return [
            'organization_id' => null,
            'vehicle_id' => Vehicle::factory(),
            'user_id' => User::factory(),
            'energy_type' => EnergyLog::TYPE_FUEL,
            'fuel_kind' => EnergyLog::FUEL_DIESEL,
            'unit' => EnergyLog::UNIT_LITER,
            'quantity' => fake()->randomFloat(2, 5, 70),
            'cost_total' => fake()->randomFloat(2, 10, 130),
            'odometer_km' => fake()->numberBetween(10000, 200000),
            'distance_since_last' => null,
            'location_address' => fake()->city(),
            'location_lat' => null,
            'location_lng' => null,
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(5),
            'duration_minutes' => 5,
            'soc_before' => null,
            'soc_after' => null,
            'charger_type' => null,
            'notes' => null,
        ];
    }

    public function fuel(): self {
        return $this->state(fn() => [
            'energy_type' => EnergyLog::TYPE_FUEL,
            'fuel_kind' => EnergyLog::FUEL_DIESEL,
            'unit' => EnergyLog::UNIT_LITER,
        ]);
    }

    public function electric(): self {
        return $this->state(fn() => [
            'energy_type' => EnergyLog::TYPE_ELECTRIC,
            'fuel_kind' => null,
            'unit' => EnergyLog::UNIT_KWH,
            'quantity' => fake()->randomFloat(2, 5, 50),
            'soc_before' => fake()->numberBetween(10, 40),
            'soc_after' => fake()->numberBetween(60, 100),
            'charger_type' => EnergyLog::CHARGER_LEVEL2,
        ]);
    }
}
