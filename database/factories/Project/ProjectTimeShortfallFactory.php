<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Project;

use App\Enums\Project\ShortfallStatus;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectTimeShortfall> */
class ProjectTimeShortfallFactory extends Factory {
    protected $model = ProjectTimeShortfall::class;

    public function definition(): array {
        return [
            'project_id' => Project::factory(),
            'organization_id' => fn (array $attributes): int => (int) Project::query()->whereKey($attributes['project_id'])->value('organization_id'),
            'user_id' => User::factory(),
            'date' => '2030-03-10',
            'target_minutes' => 480,
            'booked_minutes' => 390,
            'shortfall_minutes' => 90,
            'tolerance_minutes' => 15,
            'status' => ShortfallStatus::Open->value,
        ];
    }
}
