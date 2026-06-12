<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosureFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\TimeApproval\DayClosureStatus;
use App\Models\{DayClosure, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayClosure>
 */
class DayClosureFactory extends Factory {
    protected $model = DayClosure::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'user_id' => User::factory(),
            'day' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'status' => DayClosureStatus::Open->value,
            'attendance_locked' => false,
        ];
    }

    public function closed(): self {
        return $this->state(fn() => [
            'status' => DayClosureStatus::Closed->value,
            'closed_at' => now(),
        ]);
    }
}
