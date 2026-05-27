<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildingFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Building, Organization, Site};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory {
    protected $model = Building::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'site_id' => Site::factory(),
            'name' => fake()->randomElement(['Hauptgebäude', 'Anbau', 'Lager', 'Werkstatt']),
            'code' => strtoupper(fake()->unique()->bothify('B-###')),
            'gross_area_m2' => fake()->randomFloat(2, 50, 5000),
            'year_built' => fake()->numberBetween(1950, 2025),
            'notes' => null,
        ];
    }
}
