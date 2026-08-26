<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Safety;

use App\Models\Safety\SafetyInstruction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyInstruction>
 */
class SafetyInstructionFactory extends Factory {
    protected $model = SafetyInstruction::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'instruction_no' => fake()->unique()->numberBetween(1, 100000),
            'topic' => fake()->randomElement(['Erste Hilfe', 'Brandschutz', 'PSA', 'Gefahrstoffe']),
            'hazard_assessment_id' => null,
            'held_on' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'instructor_user_id' => null,
            'repeat_interval_months' => 12,
            'notes' => null,
            'created_by_user_id' => null,
        ];
    }
}
