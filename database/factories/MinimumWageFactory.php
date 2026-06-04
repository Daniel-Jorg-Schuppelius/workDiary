<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MinimumWageFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\MinimumWage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinimumWage>
 */
class MinimumWageFactory extends Factory {
    protected $model = MinimumWage::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'valid_from' => '2025-01-01',
            'hourly_amount' => '12.82',
        ];
    }
}
