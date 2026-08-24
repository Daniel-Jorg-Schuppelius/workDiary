<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{PaymentRun, PaymentRunItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRunItem>
 */
class PaymentRunItemFactory extends Factory {
    protected $model = PaymentRunItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'payment_run_id' => PaymentRun::factory(),
            'party_name' => mb_substr($this->faker->company(), 0, 70),
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'amount' => '100.00',
            'reference' => 'RE-' . $this->faker->unique()->numerify('######'),
        ];
    }
}
