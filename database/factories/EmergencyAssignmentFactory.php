<?php

namespace Database\Factories;

use App\Models\EmergencyAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyAssignment>
 */
class EmergencyAssignmentFactory extends Factory {
    protected $model = EmergencyAssignment::class;

    public function definition(): array {
        $start = fake()->dateTimeBetween('-1 week', '+1 week');
        $end = (clone $start)->modify('+1 hour');

        return [
            'user_id' => User::factory(),
            'on_call_shift_id' => null,
            'start_at' => $start,
            'end_at' => $end,
            'reason' => fake()->sentence(),
            'is_archived' => false,
        ];
    }
}
