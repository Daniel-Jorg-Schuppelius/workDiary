<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\{TransferChannel, TransferStatus, TransferTarget};
use App\Models\Customer;
use App\Models\Finance\BillingTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingTransfer>
 */
class BillingTransferFactory extends Factory {
    protected $model = BillingTransfer::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'customer_id' => Customer::factory(),
            'channel' => TransferChannel::Time,
            'target' => TransferTarget::Datev,
            'status' => TransferStatus::Draft,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'position_count' => 0,
            'payload_hash' => hash('sha256', $this->faker->uuid()),
        ];
    }
}
