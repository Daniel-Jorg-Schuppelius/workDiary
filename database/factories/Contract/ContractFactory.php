<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Contract;

use App\Enums\Contract\{ContractKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Models\Contract\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory {
    protected $model = Contract::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'number' => 'V-' . fake()->unique()->numerify('######'),
            'title' => 'Wartungsvertrag ' . fake()->company(),
            'kind' => ContractKind::Maintenance,
            'status' => ContractStatus::Draft,
            'partner_type' => ContractPartnerType::Other,
            'partner_name' => fake()->company(),
            'term_kind' => ContractTermKind::Fixed,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->startOfYear()->addYear()->toDateString(),
            'indexation_method' => IndexationMethod::None,
            'currency' => 'EUR',
            'value_period' => 'once',
        ];
    }
}
