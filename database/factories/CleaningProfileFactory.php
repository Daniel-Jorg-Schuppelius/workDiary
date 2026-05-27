<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CleaningProfileFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{CleaningProfile, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CleaningProfile> */
class CleaningProfileFactory extends Factory {
    protected $model = CleaningProfile::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->bothify('CP-####')),
            'label' => fake()->words(2, true),
            'interval_days' => fake()->randomElement([1, 7, 14, 30, 90]),
            'requirements' => [
                'glass' => false,
                'disinfection' => false,
                'hygiene_protocol' => false,
                'ppe' => false,
                'footwear_change' => false,
                'gowning' => false,
            ],
            'notes' => null,
            'is_active' => true,
        ];
    }
}
