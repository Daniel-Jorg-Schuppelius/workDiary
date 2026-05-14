<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'task_id' => null,
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'minutes' => fake()->numberBetween(15, 480),
            'description' => null,
        ];
    }
}
