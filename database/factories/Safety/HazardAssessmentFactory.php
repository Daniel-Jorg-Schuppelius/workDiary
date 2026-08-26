<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Safety;

use App\Enums\Safety\HazardAssessmentStatus;
use App\Models\Safety\HazardAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HazardAssessment>
 */
class HazardAssessmentFactory extends Factory {
    protected $model = HazardAssessment::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'assessment_no' => fake()->unique()->numberBetween(1, 100000),
            'version' => 1,
            'supersedes_id' => null,
            'area' => fake()->randomElement(['Werkstatt', 'Lager', 'Büro', 'Baustelle']),
            'activity' => fake()->optional()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => HazardAssessmentStatus::Draft->value,
            'review_due_on' => null,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'created_by_user_id' => null,
        ];
    }

    public function approved(?int $approvedByUserId = null): self {
        return $this->state(fn() => [
            'status' => HazardAssessmentStatus::Approved->value,
            'approved_by_user_id' => $approvedByUserId,
            'approved_at' => now(),
        ]);
    }
}
