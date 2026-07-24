<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerBillingAgreementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Billing;

use App\Enums\Billing\BillingAgreementMode;
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBillingAgreement>
 */
class CustomerBillingAgreementFactory extends Factory {
    protected $model = CustomerBillingAgreement::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'customer_id' => Customer::factory(),
            'mode' => BillingAgreementMode::Account,
            'currency' => 'EUR',
            'expected_monthly_amount' => null,
            'workdays_per_week' => 6,
            'opening_balance' => 0,
            'opening_balance_date' => null,
            'active' => true,
            'notes' => null,
        ];
    }

    public function invoiceMode(): static {
        return $this->state(['mode' => BillingAgreementMode::Invoice]);
    }
}
