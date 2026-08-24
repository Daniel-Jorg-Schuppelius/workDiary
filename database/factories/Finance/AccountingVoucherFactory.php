<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\AccountingVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingVoucher>
 */
class AccountingVoucherFactory extends Factory {
    protected $model = AccountingVoucher::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'plugin_id' => 'lexoffice',
            'external_id' => $this->faker->unique()->uuid(),
            'voucher_type' => 'invoice',
            'voucher_status' => 'open',
            'voucher_number' => 'RE-' . $this->faker->unique()->numerify('######'),
            'voucher_date' => now()->toDateString(),
            'total_amount' => '119.00',
            'net_amount' => '100.00',
            'open_amount' => '119.00',
            'currency' => 'EUR',
            'archived' => false,
            'synced_at' => now(),
        ];
    }
}
