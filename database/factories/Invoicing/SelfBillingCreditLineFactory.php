<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingCreditLineFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Invoicing;

use App\Models\Invoicing\{SelfBillingCredit, SelfBillingCreditLine};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelfBillingCreditLine>
 */
class SelfBillingCreditLineFactory extends Factory {
    protected $model = SelfBillingCreditLine::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'self_billing_credit_id' => SelfBillingCredit::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'quantity' => '1.0000',
            'unit' => 'Std.',
            'unit_price' => '60.0000',
            'tax_rate' => '19.00',
        ];
    }
}
