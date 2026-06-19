<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockMovementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Models\{ArticleVariant, StockMovement, Warehouse};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory {
    protected $model = StockMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'article_variant_id' => ArticleVariant::factory(),
            'warehouse_id' => Warehouse::factory(),
            'stock_state' => StockState::Physical->value,
            'ownership_type' => OwnershipType::Own->value,
            'movement_type' => StockMovementType::Receipt->value,
            'qty_base' => '1',
            'occurred_at' => now(),
        ];
    }
}
