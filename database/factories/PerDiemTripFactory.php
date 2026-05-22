<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Expense\PerDiemTripStatus;
use App\Models\PerDiemTrip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerDiemTrip>
 */
class PerDiemTripFactory extends Factory {
    protected $model = PerDiemTrip::class;

    public function definition(): array {
        $start = $this->faker->dateTimeBetween('-1 month', 'now');
        $end = (clone $start)->modify('+2 days +4 hours');

        return [
            'user_id' => User::factory(),
            'country' => 'DE',
            'purpose' => $this->faker->sentence(3),
            'location' => $this->faker->city(),
            'workplace_key' => null,
            'started_at' => $start,
            'ended_at' => $end,
            'accommodation_provided' => false,
            'status' => PerDiemTripStatus::Draft,
            'notes' => null,
        ];
    }
}
