<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Travel\TravelLogVehicle;
use App\Models\{TravelLog, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TravelLog>
 */
class TravelLogFactory extends Factory {
    protected $model = TravelLog::class;

    public function definition(): array {
        $start = Carbon::instance(fake()->dateTimeBetween('-30 days', 'now'));
        $end = (clone $start)->addMinutes(fake()->numberBetween(10, 180));

        return [
            'organization_id' => null,
            'user_id' => User::factory(),
            'project_id' => null,
            'task_id' => null,
            'customer_id' => null,
            'attendance_id' => null,
            'date' => $start->copy()->startOfDay(),
            'started_at' => $start,
            'ended_at' => $end,
            'from_address' => fake()->address(),
            'to_address' => fake()->address(),
            'distance_km' => fake()->randomFloat(2, 1, 250),
            'vehicle' => TravelLogVehicle::Private_->value,
            'purpose' => fake()->sentence(4),
            'round_trip' => false,
            'reimbursable' => true,
            'rate_per_km' => '0.3000',
        ];
    }
}
