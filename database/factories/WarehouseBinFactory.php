<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseBinFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Warehouse, WarehouseBin};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseBin>
 */
class WarehouseBinFactory extends Factory {
    protected $model = WarehouseBin::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'code' => 'R' . fake()->unique()->numberBetween(1, 9999),
            'name' => null,
            'active' => true,
            'blocked' => false,
            'sort_order' => 0,
        ];
    }

    public function blocked(): static {
        return $this->state(fn (): array => ['blocked' => true]);
    }
}
