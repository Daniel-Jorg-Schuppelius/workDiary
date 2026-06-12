<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsCorrectiveActionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\CorrectiveActionStatus;
use App\Models\Isms\{IsmsAuditFinding, IsmsCorrectiveAction};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsCorrectiveAction>
 */
class IsmsCorrectiveActionFactory extends Factory {
    protected $model = IsmsCorrectiveAction::class;

    public function definition(): array {
        return [
            'isms_audit_finding_id' => IsmsAuditFinding::factory(),
            'title' => fake()->sentence(4),
            'root_cause' => fake()->sentence(8),
            'action_plan' => fake()->paragraph(),
            'owner_user_id' => null,
            'due_on' => now()->addMonth()->toDateString(),
            'status' => CorrectiveActionStatus::Open->value,
            'effectiveness_note' => null,
            'completed_on' => null,
        ];
    }

    public function status(CorrectiveActionStatus $status): self {
        return $this->state(fn() => ['status' => $status->value]);
    }

    /** Überfällig (Fristen-Scanner isms.correctiveActionOverdue). */
    public function overdue(): self {
        return $this->state(fn() => [
            'due_on' => now()->subDays(3)->toDateString(),
            'status' => CorrectiveActionStatus::Open->value,
        ]);
    }

    /** Umgesetzt (Wirksamkeitsprüfung möglich). */
    public function done(): self {
        return $this->state(fn() => [
            'status' => CorrectiveActionStatus::Done->value,
            'completed_on' => now()->subDay()->toDateString(),
        ]);
    }

    /** Wirksam geprüft (mit Pflicht-Notiz). */
    public function effective(): self {
        return $this->state(fn() => [
            'status' => CorrectiveActionStatus::Effective->value,
            'completed_on' => now()->subDay()->toDateString(),
            'effectiveness_note' => 'Wirksamkeit durch Stichprobe nachgewiesen.',
        ]);
    }
}
