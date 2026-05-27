<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandoverFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Models\{Asset, KeyHandover, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KeyHandover> */
class KeyHandoverFactory extends Factory {
    protected $model = KeyHandover::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_id' => Asset::factory(),
            'direction' => KeyHandoverDirection::Out->value,
            'person_name' => fake()->name(),
            'occurred_at' => now(),
        ];
    }
}
