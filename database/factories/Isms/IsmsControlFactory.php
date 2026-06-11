<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsControlFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\{ControlImplementationStatus, ControlSource};
use App\Models\Isms\IsmsControl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsControl>
 */
class IsmsControlFactory extends Factory {
    protected $model = IsmsControl::class;

    public function definition(): array {
        return [
            'code' => 'M-' . fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(4),
            'description' => null,
            'source' => ControlSource::Custom->value,
            'applicable' => true,
            'justification' => null,
            'implementation_status' => ControlImplementationStatus::Open->value,
            'evidence_note' => null,
            'owner_user_id' => null,
        ];
    }

    public function annexA(string $code = 'A.5.1', string $title = 'Informationssicherheitsrichtlinien'): self {
        return $this->state(fn() => [
            'code' => $code,
            'title' => $title,
            'source' => ControlSource::Iso27001AnnexA->value,
        ]);
    }

    public function notApplicable(string $justification = 'Nicht zutreffend.'): self {
        return $this->state(fn() => [
            'applicable' => false,
            'justification' => $justification,
            'implementation_status' => ControlImplementationStatus::NotApplicable->value,
        ]);
    }
}
