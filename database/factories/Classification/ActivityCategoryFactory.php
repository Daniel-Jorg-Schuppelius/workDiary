<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategoryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Activity\ActivityCategoryType;
use App\Models\ActivityCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityCategory>
 */
class ActivityCategoryFactory extends Factory {
    protected $model = ActivityCategory::class;

    public function definition(): array {
        $type = fake()->randomElement(ActivityCategoryType::cases());

        return [
            'organization_id' => null,
            'key' => fake()->unique()->slug(2),
            'label' => ucfirst($type->value) . ' ' . fake()->word(),
            'activity_type' => $type,
            'billable_default' => false,
            'counts_as_work' => $type !== ActivityCategoryType::Absence
                && $type !== ActivityCategoryType::Break_,
            'color' => fake()->hexColor(),
            'icon' => null,
            'sort_order' => 100,
            'active' => true,
            'description' => null,
        ];
    }
}
