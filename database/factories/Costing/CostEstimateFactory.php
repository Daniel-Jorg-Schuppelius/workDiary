<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostEstimateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Costing;

use App\Models\Costing\CostEstimate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostEstimate>
 */
class CostEstimateFactory extends Factory {
    protected $model = CostEstimate::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'name' => 'Kostenschätzung ' . fake()->sentence(2),
            'stage' => CostEstimate::STAGE_ESTIMATE,
            'method' => 'cost by elements',
            'determined_on' => now()->toDateString(),
            'currency' => 'EUR',
            'source' => CostEstimate::SOURCE_MANUAL,
        ];
    }
}
