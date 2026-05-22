<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\PerDiemRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerDiemRate>
 */
class PerDiemRateFactory extends Factory {
    protected $model = PerDiemRate::class;

    public function definition(): array {
        return [
            'country' => 'DE',
            'valid_from' => '2024-01-01',
            'valid_to' => null,
            'full_day_amount' => '28.00',
            'partial_day_amount' => '14.00',
            'overnight_amount' => '20.00',
            'currency' => 'EUR',
            'source' => 'Factory',
        ];
    }
}
