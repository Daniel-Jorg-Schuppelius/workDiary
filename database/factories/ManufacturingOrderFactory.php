<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ManufacturingOrder};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturingOrder>
 */
class ManufacturingOrderFactory extends Factory {
    protected $model = ManufacturingOrder::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'number' => null,
            'article_id' => Article::factory(),
            'target_qty' => '1',
            'unit' => 'Stk',
            'status' => ManufacturingOrderStatus::Draft->value,
            'priority' => 100,
        ];
    }
}
