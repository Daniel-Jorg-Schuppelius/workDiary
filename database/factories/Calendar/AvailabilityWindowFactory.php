<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AvailabilityWindowFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Shift\AvailabilityKind;
use App\Models\{AvailabilityWindow, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityWindow>
 */
class AvailabilityWindowFactory extends Factory {
    protected $model = AvailabilityWindow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'weekday' => fake()->numberBetween(1, 5),
            'specific_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'kind' => AvailabilityKind::Available,
            'valid_from' => null,
            'valid_until' => null,
            'note' => null,
        ];
    }

    public function forWeekday(int $weekday): static {
        return $this->state(['weekday' => $weekday, 'specific_date' => null]);
    }

    public function forDate(string $date): static {
        return $this->state(['specific_date' => $date, 'weekday' => null]);
    }

    public function unavailable(): static {
        return $this->state(['kind' => AvailabilityKind::Unavailable]);
    }

    public function preferred(): static {
        return $this->state(['kind' => AvailabilityKind::Preferred]);
    }
}
