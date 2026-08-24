<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferPositionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingTransferPosition>
 */
class BillingTransferPositionFactory extends Factory {
    protected $model = BillingTransferPosition::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'billing_transfer_id' => BillingTransfer::factory(),
            'position' => 1,
            'source_kind' => 'time',
            'name' => 'Dienstleistung ' . $this->faker->words(2, true),
            'quantity' => '1.000',
            'unit_name' => 'Std.',
            'unit_price' => '95.0000',
            'vat_rate' => '19.00',
            'amount' => '95.00',
        ];
    }
}
