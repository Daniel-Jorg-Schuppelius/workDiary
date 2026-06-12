<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRiskAssessmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{AssessmentKind, AssessmentStatus};
use App\Models\Isms\{IsmsRisk, IsmsRiskAssessment};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsRiskAssessment>
 */
class IsmsRiskAssessmentFactory extends Factory {
    protected $model = IsmsRiskAssessment::class;

    public function definition(): array {
        $likelihood = fake()->numberBetween(1, 5);
        $impact = fake()->numberBetween(1, 5);

        return [
            'isms_risk_id' => IsmsRisk::factory(),
            'assessment_no' => fake()->unique()->numberBetween(1, 999999),
            'kind' => AssessmentKind::Net->value,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'score' => $likelihood * $impact,
            'rationale' => fake()->sentence(),
            'status' => AssessmentStatus::Draft->value,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'valid_until' => null,
            'created_by_user_id' => null,
        ];
    }

    public function approved(?int $approvedByUserId = null): self {
        return $this->state(fn() => [
            'status' => AssessmentStatus::Approved->value,
            'approved_by_user_id' => $approvedByUserId,
            'approved_at' => now(),
        ]);
    }

    public function gross(): self {
        return $this->state(fn() => ['kind' => AssessmentKind::Gross->value]);
    }

    public function net(): self {
        return $this->state(fn() => ['kind' => AssessmentKind::Net->value]);
    }

    public function target(): self {
        return $this->state(fn() => ['kind' => AssessmentKind::Target->value]);
    }
}
