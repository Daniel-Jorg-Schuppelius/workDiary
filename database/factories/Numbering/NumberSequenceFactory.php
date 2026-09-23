<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberSequenceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Numbering\NumberScope;
use App\Models\{NumberSequence, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NumberSequence> */
class NumberSequenceFactory extends Factory {
    protected $model = NumberSequence::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'scope' => NumberScope::Asset->value,
            'period' => (string) now()->year,
            'last_value' => 0,
        ];
    }
}
