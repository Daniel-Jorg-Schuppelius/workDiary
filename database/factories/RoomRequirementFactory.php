<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomRequirementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Facility\RoomRequirementKind;
use App\Models\{Organization, Room, RoomRequirement};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomRequirement>
 */
class RoomRequirementFactory extends Factory {
    protected $model = RoomRequirement::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'room_id' => Room::factory(),
            'kind' => fake()->randomElement(RoomRequirementKind::cases())->value,
            'level' => fake()->randomElement(['1', '2', '3', 'hoch', null]),
            'note' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
