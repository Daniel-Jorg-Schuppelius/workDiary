<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsApplicabilityStatementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Isms\IsmsApplicabilityStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsApplicabilityStatement>
 */
class IsmsApplicabilityStatementFactory extends Factory {
    protected $model = IsmsApplicabilityStatement::class;

    public function definition(): array {
        return [
            'applicable' => true,
            'justification' => null,
            'implementation_status' => ControlImplementationStatus::Open->value,
            'evidence_note' => null,
        ];
    }

    public function notApplicable(string $justification = 'Nicht zutreffend.'): self {
        return $this->state(fn() => [
            'applicable' => false,
            'justification' => $justification,
            'implementation_status' => ControlImplementationStatus::NotApplicable->value,
        ]);
    }
}
