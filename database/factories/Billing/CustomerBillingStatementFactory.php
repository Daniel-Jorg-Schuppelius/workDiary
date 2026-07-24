<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerBillingStatementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Billing;

use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingStatement};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBillingStatement>
 */
class CustomerBillingStatementFactory extends Factory {
    protected $model = CustomerBillingStatement::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'customer_billing_agreement_id' => CustomerBillingAgreement::factory(),
            'year' => 2026,
            'month' => $this->faker->numberBetween(1, 12),
            'total_minutes' => 0,
            'gross_value' => 0,
            'payments_total' => 0,
            'carry_in' => 0,
            'balance' => 0,
            'locked' => false,
        ];
    }
}
