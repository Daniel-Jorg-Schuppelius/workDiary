<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerBillingRateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Billing;

use App\Enums\Billing\BillingRateDayType;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBillingRate>
 */
class CustomerBillingRateFactory extends Factory {
    protected $model = CustomerBillingRate::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null,
            'customer_billing_agreement_id' => CustomerBillingAgreement::factory(),
            'activity_category_id' => null,
            'day_type' => BillingRateDayType::Weekday,
            'hourly_rate' => $this->faker->randomFloat(2, 10, 120),
            'valid_from' => null,
            'valid_until' => null,
        ];
    }

    public function weekend(): static {
        return $this->state(['day_type' => BillingRateDayType::Weekend]);
    }
}
