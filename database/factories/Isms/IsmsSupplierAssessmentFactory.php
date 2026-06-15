<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSupplierAssessmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{IncidentSeverity, SupplierAssessmentStatus};
use App\Models\Isms\IsmsSupplierAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsSupplierAssessment>
 */
class IsmsSupplierAssessmentFactory extends Factory {
    protected $model = IsmsSupplierAssessment::class;

    public function definition(): array {
        return [
            'assessment_no' => fake()->unique()->numberBetween(1, 999999),
            'supplier_id' => null,
            'supplier_name' => fake()->company(),
            'criticality' => IncidentSeverity::Medium->value,
            'service_description' => fake()->sentence(6),
            'isms_scope_id' => null,
            'security_requirements' => null,
            'has_nda' => false,
            'has_dpa' => false,
            'dpa_ref' => null,
            'audit_right' => false,
            'last_review_on' => null,
            'next_review_on' => null,
            'risk_rating' => IncidentSeverity::Medium->value,
            'status' => SupplierAssessmentStatus::Draft->value,
            'findings' => null,
            'owner_user_id' => null,
        ];
    }

    public function approved(): self {
        return $this->state(fn() => ['status' => SupplierAssessmentStatus::Approved->value]);
    }

    public function flagged(): self {
        return $this->state(fn() => ['status' => SupplierAssessmentStatus::Flagged->value]);
    }

    /** Überfälliger Review (next_review_on in der Vergangenheit, nicht freigegeben). */
    public function reviewOverdue(): self {
        return $this->state(fn() => [
            'status' => SupplierAssessmentStatus::Assessed->value,
            'next_review_on' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function critical(): self {
        return $this->state(fn() => [
            'criticality' => IncidentSeverity::Critical->value,
            'risk_rating' => IncidentSeverity::Critical->value,
        ]);
    }
}
