<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageRequirementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\CoverageRequirement;
use App\Models\ShiftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageRequirement>
 */
class CoverageRequirementFactory extends Factory {
    protected $model = CoverageRequirement::class;

    public function definition(): array {
        return [
            'duty_plan_id' => null,
            'shift_type_id' => ShiftType::factory(),
            'weekday' => fake()->numberBetween(1, 5),
            'specific_date' => null,
            'min_staff' => 1,
            'max_staff' => null,
            'required_qualification_ids' => null,
            'notes' => null,
        ];
    }

    public function forDate(string $date): static {
        return $this->state([
            'specific_date' => $date,
            'weekday' => null,
        ]);
    }

    public function forWeekday(int $weekday): static {
        return $this->state([
            'weekday' => $weekday,
            'specific_date' => null,
        ]);
    }
}
