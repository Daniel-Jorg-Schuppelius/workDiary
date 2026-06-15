<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Models\ReportTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportTarget>
 */
class ReportTargetFactory extends Factory {
    protected $model = ReportTarget::class;

    public function definition(): array {
        return [
            'metric' => ReportTargetMetric::ContributionMargin,
            'scope' => ReportTargetScope::Org,
            'scope_id' => null,
            'target_value' => $this->faker->randomFloat(2, 50, 90),
            'period' => null,
            'valid_from' => null,
            'valid_until' => null,
            'note' => null,
        ];
    }

    public function metric(ReportTargetMetric $metric): static {
        return $this->state(['metric' => $metric]);
    }

    public function forScope(ReportTargetScope $scope, ?int $scopeId): static {
        return $this->state(['scope' => $scope, 'scope_id' => $scopeId]);
    }

    public function value(float $value): static {
        return $this->state(['target_value' => $value]);
    }
}
