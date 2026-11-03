<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeTargetFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Project;

use App\Models\Project\{Project, ProjectTimeTarget};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectTimeTarget> */
class ProjectTimeTargetFactory extends Factory {
    protected $model = ProjectTimeTarget::class;

    public function definition(): array {
        return [
            'project_id' => Project::factory(),
            'organization_id' => fn (array $attributes): int => (int) Project::query()->whereKey($attributes['project_id'])->value('organization_id'),
            'user_id' => null,
            'weekday' => 1,
            'specific_date' => null,
            'target_minutes' => 480,
        ];
    }

    public function onDate(string $date): static {
        return $this->state(['weekday' => null, 'specific_date' => $date]);
    }
}
