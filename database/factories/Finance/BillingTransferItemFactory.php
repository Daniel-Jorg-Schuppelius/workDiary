<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{BillingTransfer, BillingTransferItem};
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingTransferItem>
 */
class BillingTransferItemFactory extends Factory {
    protected $model = BillingTransferItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        // Quelle wird von den Tests explizit gesetzt. Default = Platzhalter
        // (Muster PaymentAllocationFactory).
        return [
            'billing_transfer_id' => BillingTransfer::factory(),
            'source_type' => TimeEntry::class,
            'source_id' => 1,
            'amount' => '150.00',
            'quantity' => '1.50',
        ];
    }
}
