<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockLotFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{ArticleVariant, StockLot};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory {
    protected $model = StockLot::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'article_variant_id' => ArticleVariant::factory(),
            'lot_no' => 'LOT-' . fake()->unique()->numerify('#####'),
            'status' => StockLot::STATUS_ACTIVE,
        ];
    }
}
