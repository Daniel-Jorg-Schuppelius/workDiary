<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostEstimateItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Costing;

use App\Models\Costing\{CostEstimate, CostEstimateItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostEstimateItem>
 */
class CostEstimateItemFactory extends Factory {
    protected $model = CostEstimateItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'cost_estimate_id' => CostEstimate::factory(),
            'code' => '300',
            'label' => 'Bauwerk – Baukonstruktionen',
            'quantity' => '1.0000',
            'unit' => 'psch',
            'unit_price' => '1000.0000',
            'amount' => '1000.00',
            'level' => 1,
            'position' => 1,
        ];
    }
}
