<?php

namespace Database\Factories;

use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftType>
 */
class ShiftTypeFactory extends Factory {
    protected $model = ShiftType::class;

    public function definition(): array {
        $name = fake()->randomElement(['Frühdienst', 'Spätdienst', 'Nachtdienst', 'Wochenenddienst', 'Rufbereitschaft']);
        $abbr = mb_strtoupper(mb_substr($name, 0, 2));
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

        return [
            'name'               => $name,
            'abbreviation'       => $abbr,
            'color'              => fake()->randomElement($colors),
            'default_start_time' => fake()->randomElement(['06:00', '08:00', '14:00', '22:00', null]),
            'default_end_time'   => fake()->randomElement(['14:00', '16:00', '22:00', '06:00', null]),
            'is_active'          => true,
        ];
    }

    public function inactive(): static {
        return $this->state(['is_active' => false]);
    }
}
