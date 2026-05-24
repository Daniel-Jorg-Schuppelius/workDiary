<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{ScheduledShift, ShiftType, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledShift>
 */
class ScheduledShiftFactory extends Factory {
    protected $model = ScheduledShift::class;

    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'shift_type_id' => null,
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'note' => null,
            'status' => ScheduledShiftStatus::Draft,
        ];
    }

    public function published(): static {
        return $this->state(['status' => ScheduledShiftStatus::Published]);
    }

    public function confirmed(): static {
        return $this->state(['status' => ScheduledShiftStatus::Confirmed]);
    }

    public function cancelled(): static {
        return $this->state(['status' => ScheduledShiftStatus::Cancelled]);
    }

    public function withType(): static {
        return $this->state(['shift_type_id' => ShiftType::factory()]);
    }
}
