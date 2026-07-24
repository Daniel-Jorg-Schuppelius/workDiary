<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerAccountPaymentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Billing;

use App\Enums\Billing\AccountPaymentSource;
use App\Models\Billing\{CustomerAccountPayment, CustomerBillingAgreement};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccountPayment>
 */
class CustomerAccountPaymentFactory extends Factory {
    protected $model = CustomerAccountPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'customer_billing_agreement_id' => CustomerBillingAgreement::factory(),
            'paid_on' => now()->toDateString(),
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'currency' => 'EUR',
            'source' => AccountPaymentSource::Manual,
            'bank_transaction_id' => null,
            'payment_allocation_id' => null,
            'note' => null,
            'created_by_user_id' => null,
        ];
    }
}
