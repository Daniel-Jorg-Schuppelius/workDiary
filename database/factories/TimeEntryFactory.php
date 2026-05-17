<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
            'kind' => TimeEntry::KIND_WORK,
            'activity_type' => TimeEntry::ACTIVITY_PROJECT,
        ];
    }

    /**
     * State: non-project administrative work (no project_id).
     */
    public function administration(): self
    {
        return $this->state(fn () => [
            'project_id' => null,
            'activity_type' => TimeEntry::ACTIVITY_ADMIN,
        ]);
    }

    /**
     * State: travel time.
     */
    public function travel(): self
    {
        return $this->state(fn () => [
            'project_id' => null,
            'kind' => TimeEntry::KIND_TRAVEL,
            'activity_type' => TimeEntry::ACTIVITY_TRAVEL,
        ]);
    }
}
