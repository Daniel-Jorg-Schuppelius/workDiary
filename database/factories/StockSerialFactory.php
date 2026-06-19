<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockSerialFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Models\{Article, ArticleVariant, StockSerial};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockSerial>
 */
class StockSerialFactory extends Factory {
    protected $model = StockSerial::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $variant = ArticleVariant::factory();

        return [
            'organization_id' => null,
            'article_id' => Article::factory(),
            'article_variant_id' => $variant,
            'serial_no' => 'SN-' . fake()->unique()->numerify('######'),
            'status' => SerialStatus::InStock->value,
            'source' => SerialSource::Manufactured->value,
        ];
    }
}
