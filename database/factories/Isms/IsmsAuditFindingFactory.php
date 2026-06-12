<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditFindingFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{FindingKind, FindingStatus};
use App\Models\Isms\{IsmsAudit, IsmsAuditFinding};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsAuditFinding>
 */
class IsmsAuditFindingFactory extends Factory {
    protected $model = IsmsAuditFinding::class;

    public function definition(): array {
        return [
            'isms_audit_id' => IsmsAudit::factory(),
            'finding_no' => fake()->unique()->numberBetween(1, 999999),
            'kind' => FindingKind::Observation->value,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'isms_requirement_id' => null,
            'status' => FindingStatus::Open->value,
        ];
    }

    public function kind(FindingKind $kind): self {
        return $this->state(fn() => ['kind' => $kind->value]);
    }

    public function status(FindingStatus $status): self {
        return $this->state(fn() => ['status' => $status->value]);
    }

    /** Nebenabweichung (Nichtkonformität — verschärfte Abschlussregel). */
    public function nonconformity(): self {
        return $this->kind(FindingKind::NonconformityMinor);
    }
}
