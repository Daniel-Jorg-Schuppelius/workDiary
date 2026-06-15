<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Models\{SafetyEvent, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyEvent>
 */
class SafetyEventFactory extends Factory {
    protected $model = SafetyEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'event_no' => fake()->unique()->numberBetween(1, 100000),
            'kind' => SafetyEventKind::Hazard->value,
            'severity' => SafetyEventSeverity::Low->value,
            'occurred_at' => now()->subDays(fake()->numberBetween(0, 10)),
            'location' => fake()->optional()->streetName(),
            'subject_type' => null,
            'subject_id' => null,
            'reported_by_user_id' => User::factory(),
            'affected_person' => fake()->optional()->name(),
            'description' => fake()->paragraph(),
            'immediate_action' => fake()->optional()->sentence(),
            'status' => SafetyEventStatus::Reported->value,
            'root_cause' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ];
    }

    public function accident(): self {
        return $this->state(fn (): array => [
            'kind' => SafetyEventKind::Accident->value,
            'severity' => SafetyEventSeverity::High->value,
        ]);
    }

    public function critical(): self {
        return $this->state(fn (): array => [
            'severity' => SafetyEventSeverity::Critical->value,
        ]);
    }

    public function closed(): self {
        return $this->state(fn (): array => [
            'status' => SafetyEventStatus::Closed->value,
            'root_cause' => fake()->sentence(),
            'closed_at' => now(),
        ]);
    }
}
