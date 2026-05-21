<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory {
    protected $model = Room::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Raum ' . fake()->unique()->numberBetween(1, 999),
            'code' => strtoupper(fake()->unique()->bothify('R-###')),
            'building' => fake()->randomElement(['Hauptgebäude', 'Anbau', 'Lager']),
            'floor' => fake()->randomElement(['EG', '1.OG', '2.OG']),
            'capacity' => fake()->numberBetween(2, 60),
            'equipment' => fake()->randomElements(
                ['beamer', 'whiteboard', 'video_conf', 'flipchart', 'screen'],
                fake()->numberBetween(0, 3),
            ),
            'color' => fake()->hexColor(),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): self {
        return $this->state(fn(): array => ['is_active' => false]);
    }
}
