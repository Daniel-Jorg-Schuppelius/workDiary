<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Product\ProductStatus;
use App\Models\{Organization, Product};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory {
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $manufacturer = fake()->company();
        $model = strtoupper(fake()->bothify('??-###'));

        return [
            'organization_id' => Organization::factory(),
            'manufacturer' => $manufacturer,
            'model' => $model,
            'name' => $manufacturer . ' ' . $model,
            'status' => ProductStatus::Active,
        ];
    }
}
