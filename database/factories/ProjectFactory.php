<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Project\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory {
    protected $model = Project::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'customer_id' => null,
            'name' => fake()->unique()->words(2, true),
            'status' => ProjectStatus::Active->value,
            'billable' => true,
            'global_activities' => true,
        ];
    }
}
