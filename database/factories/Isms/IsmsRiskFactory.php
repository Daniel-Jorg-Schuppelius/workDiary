<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRiskFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{RiskCategory, RiskStatus, RiskTreatment};
use App\Models\Isms\IsmsRisk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsRisk>
 */
class IsmsRiskFactory extends Factory {
    protected $model = IsmsRisk::class;

    public function definition(): array {
        $likelihood = fake()->numberBetween(1, 5);
        $impact = fake()->numberBetween(1, 5);

        return [
            'risk_no' => fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(RiskCategory::cases())->value,
            'asset_ref' => null,
            'threat' => null,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'score' => $likelihood * $impact,
            'treatment' => RiskTreatment::Mitigate->value,
            'status' => RiskStatus::Identified->value,
            'owner_user_id' => null,
            'review_due_on' => null,
        ];
    }

    public function closed(): self {
        return $this->state(fn() => ['status' => RiskStatus::Closed->value]);
    }

    public function reviewDue(): self {
        return $this->state(fn() => ['review_due_on' => now()->subDay()->toDateString()]);
    }
}
