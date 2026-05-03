<?php

namespace Database\Factories;

use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryEntry>
 */
class DiaryEntryFactory extends Factory {
    protected $model = DiaryEntry::class;

    public function definition(): array {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');
        $end = (clone $start)->modify('+1 hour');

        return [
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
            'response' => null,
            'status' => 2,
            'start_at' => $start,
            'end_at' => $end,
            'is_archived' => false,
            'archived_at' => null,
        ];
    }
}
