<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Safety;

use App\Models\Safety\{HazardAssessment, HazardAssessmentItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HazardAssessmentItem>
 */
class HazardAssessmentItemFactory extends Factory {
    protected $model = HazardAssessmentItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $severity = fake()->numberBetween(1, 5);
        $likelihood = fake()->numberBetween(1, 5);

        return [
            'hazard_assessment_id' => HazardAssessment::factory(),
            'position' => 1,
            'hazard' => fake()->sentence(4),
            'measure' => fake()->optional()->sentence(),
            'severity_before' => $severity,
            'likelihood_before' => $likelihood,
            'risk_before' => $severity * $likelihood,
            'severity_after' => null,
            'likelihood_after' => null,
            'risk_after' => null,
        ];
    }
}
