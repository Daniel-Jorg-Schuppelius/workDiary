<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContractFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Organization, SlaContract};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SlaContract> */
class SlaContractFactory extends Factory {
    protected $model = SlaContract::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => null,
            'code' => strtoupper(fake()->bothify('SLA-####')),
            'label' => 'Standard-SLA',
            'priority_table' => [
                'low'    => ['reaction_minutes' => 1440, 'resolution_minutes' => 10080],
                'normal' => ['reaction_minutes' => 480,  'resolution_minutes' => 2880],
                'high'   => ['reaction_minutes' => 120,  'resolution_minutes' => 1440],
                'urgent' => ['reaction_minutes' => 30,   'resolution_minutes' => 240],
            ],
            'business_hours' => null,
            'escalation_chain' => null,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
