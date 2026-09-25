<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTemplateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Contract;

use App\Enums\Contract\ContractKind;
use App\Models\Contract\ContractTemplate;
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory {
    protected $model = ContractTemplate::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Wartungsvertrag ' . fake()->word(),
            'kind' => ContractKind::Maintenance->value,
            'notice_period_days' => 90,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'obligations' => [],
            'is_active' => true,
        ];
    }
}
