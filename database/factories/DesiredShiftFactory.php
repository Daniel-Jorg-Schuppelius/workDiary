<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DesiredShiftFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Shift\ShiftPreference;
use App\Models\{DesiredShift, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesiredShift>
 */
class DesiredShiftFactory extends Factory {
    protected $model = DesiredShift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'shift_type_id' => null,
            'preference' => ShiftPreference::Want,
            'note' => null,
        ];
    }

    public function want(): static {
        return $this->state(['preference' => ShiftPreference::Want]);
    }

    public function avoid(): static {
        return $this->state(['preference' => ShiftPreference::Avoid]);
    }
}
