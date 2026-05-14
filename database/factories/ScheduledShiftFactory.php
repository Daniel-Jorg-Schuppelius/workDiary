<?php

namespace Database\Factories;

use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledShift>
 */
class ScheduledShiftFactory extends Factory
{
    protected $model = ScheduledShift::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shift_type_id' => null,
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'note' => null,
            'status' => ScheduledShift::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ScheduledShift::STATUS_PUBLISHED]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => ScheduledShift::STATUS_CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => ScheduledShift::STATUS_CANCELLED]);
    }

    public function withType(): static
    {
        return $this->state(['shift_type_id' => ShiftType::factory()]);
    }
}
