<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Numbering\NumberScope;
use App\Models\{NumberFormat, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NumberFormat> */
class NumberFormatFactory extends Factory {
    protected $model = NumberFormat::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'scope' => NumberScope::Asset->value,
            'prefix' => 'AS',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ];
    }
}
