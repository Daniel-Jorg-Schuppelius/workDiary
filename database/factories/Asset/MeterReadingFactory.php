<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Asset;

use App\Models\Asset\{Asset, MeterReading};
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeterReading> */
class MeterReadingFactory extends Factory {
    protected $model = MeterReading::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_id' => Asset::factory(),
            'read_at' => now(),
            'value' => fake()->randomFloat(4, 100, 10000),
            'unit' => 'kWh',
        ];
    }
}
