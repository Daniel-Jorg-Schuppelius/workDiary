<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FloorFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Building, Floor, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Floor>
 */
class FloorFactory extends Factory {
    protected $model = Floor::class;

    protected static int $nextLevel = 0;

    public function definition(): array {
        $level = self::$nextLevel++;
        $label = match (true) {
            $level < 0 => abs($level) . '. UG',
            $level === 0 => 'EG',
            default => $level . '. OG',
        };

        return [
            'organization_id' => Organization::factory(),
            'building_id' => Building::factory(),
            'level' => $level,
            'label' => $label,
            'gross_area_m2' => fake()->randomFloat(2, 50, 1000),
            'notes' => null,
        ];
    }
}
