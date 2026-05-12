<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory {
    protected $model = Milestone::class;

    public function definition(): array {
        return [
            'project_id'   => Project::factory(),
            'created_by'   => User::factory(),
            'title'        => fake()->sentence(4),
            'description'  => null,
            'due_date'     => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'is_completed' => false,
            'position'     => 0,
        ];
    }

    public function completed(): static {
        return $this->state(['is_completed' => true]);
    }
}
