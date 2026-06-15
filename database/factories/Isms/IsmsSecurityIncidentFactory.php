<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSecurityIncidentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{IncidentSeverity, SecurityIncidentCategory, SecurityIncidentStatus};
use App\Models\Isms\IsmsSecurityIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsSecurityIncident>
 */
class IsmsSecurityIncidentFactory extends Factory {
    protected $model = IsmsSecurityIncident::class;

    public function definition(): array {
        return [
            'incident_no' => fake()->unique()->numberBetween(1, 999999),
            'isms_scope_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(SecurityIncidentCategory::cases())->value,
            'severity' => fake()->randomElement(IncidentSeverity::cases())->value,
            'status' => SecurityIncidentStatus::Reported->value,
            'detected_at' => now()->subDays(fake()->numberBetween(0, 10)),
            'occurred_at' => null,
            'contained_at' => null,
            'closed_at' => null,
            'reporter_user_id' => null,
            'owner_user_id' => null,
            'impact' => null,
            'root_cause' => null,
            'lessons_learned' => null,
            'personal_data_affected' => false,
            'privacy_incident_ref' => null,
        ];
    }

    public function critical(): self {
        return $this->state(fn() => ['severity' => IncidentSeverity::Critical->value]);
    }

    public function closed(): self {
        return $this->state(fn() => [
            'status' => SecurityIncidentStatus::Closed->value,
            'root_cause' => fake()->sentence(),
            'lessons_learned' => fake()->sentence(),
            'closed_at' => now(),
        ]);
    }

    public function personalDataAffected(): self {
        return $this->state(fn() => ['personal_data_affected' => true]);
    }
}
