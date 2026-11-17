<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServiceWaiverFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\ServicePlan;

use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\ServicePlan\CustomerServiceWaiver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * „Entfällt“ für das laufende Jahr mit erfundenem Grund.
 *
 * @extends Factory<CustomerServiceWaiver>
 */
class CustomerServiceWaiverFactory extends Factory {
    protected $model = CustomerServiceWaiver::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => static fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'entry_type_id' => static fn (array $attributes) => EntryType::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'product_group_code' => null,
            'period_key' => (string) now()->year,
            'reason' => 'Kein Bedarf in diesem Jahr',
        ];
    }
}
