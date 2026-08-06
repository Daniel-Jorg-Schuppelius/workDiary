<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialCostAllocationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\{Customer, MaterialCostAllocation};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialCostAllocation>
 */
class MaterialCostAllocationFactory extends Factory {
    protected $model = MaterialCostAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'organization_id' => null, // BelongsToOrganization füllt automatisch
            'customer_id' => Customer::factory(),
            'project_id' => null,
            'source_type' => null,
            'source_id' => null,
            'description' => fake()->words(3, true),
            'allocated_amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'EUR',
            'allocated_on' => now()->toDateString(),
            'created_by' => null,
        ];
    }
}
