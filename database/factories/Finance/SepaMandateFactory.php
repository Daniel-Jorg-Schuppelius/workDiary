<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SepaMandateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\{MandateKind, MandateStatus};
use App\Models\Customer;
use App\Models\Finance\SepaMandate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SepaMandate>
 */
class SepaMandateFactory extends Factory {
    protected $model = SepaMandate::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        // iban_hash setzt das Model im saving-Hook selbst (BankHelper::hashIBAN).
        return [
            'customer_id' => Customer::factory(),
            'reference' => 'MND-' . $this->faker->unique()->numerify('######'),
            'kind' => MandateKind::Recurring,
            'status' => MandateStatus::Active,
            'signed_on' => now()->subMonths(6)->toDateString(),
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => $this->faker->name(),
        ];
    }
}
