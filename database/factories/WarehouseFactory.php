<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory {
    protected $model = Warehouse::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'code' => null,
            'name' => 'Lager ' . fake()->unique()->numberBetween(1, 9999),
            'is_default' => false,
            'active' => true,
            'blocked' => false,
        ];
    }
}
