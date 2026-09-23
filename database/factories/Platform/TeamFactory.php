<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory {
    protected $model = Team::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
        ];
    }
}
