<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillOfQuantityFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Gaeb;

use App\Models\Gaeb\BillOfQuantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillOfQuantity>
 */
class BillOfQuantityFactory extends Factory {
    protected $model = BillOfQuantity::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'name' => 'LV ' . fake()->sentence(2),
            'currency' => 'EUR',
        ];
    }
}
