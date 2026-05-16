<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory {
    protected $model = Task::class;

    public function definition(): array {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(5),
            'description' => null,
            'status' => Task::STATUS_OPEN,
            'priority' => Task::PRIORITY_MEDIUM,
            'due_date' => null,
            'position' => 0,
        ];
    }

    public function done(): static {
        return $this->state(['status' => Task::STATUS_DONE]);
    }

    public function urgent(): static {
        return $this->state(['priority' => Task::PRIORITY_URGENT]);
    }
}
