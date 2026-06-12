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

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Isms\IsmsControl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsControl>
 */
class IsmsControlFactory extends Factory {
    protected $model = IsmsControl::class;

    public function definition(): array {
        return [
            'title' => fake()->sentence(4),
            'description' => null,
            'implementation_status' => ControlImplementationStatus::Open->value,
            'evidence_note' => null,
            'owner_user_id' => null,
        ];
    }

    public function implemented(): self {
        return $this->state(fn() => [
            'implementation_status' => ControlImplementationStatus::Implemented->value,
        ]);
    }
}
