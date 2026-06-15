<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentAllocationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\AllocationKind;
use App\Models\Finance\{BankTransaction, PaymentAllocation};
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory {
    protected $model = PaymentAllocation::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        // Invoice besitzt (noch) keine eigene Factory; das Ziel der Zuordnung
        // wird daher von den Tests explizit gesetzt. Default = Platzhalter.
        return [
            'bank_transaction_id' => BankTransaction::factory(),
            'allocatable_type' => Invoice::class,
            'allocatable_id' => 1,
            'amount' => '100.00',
            'kind' => AllocationKind::Payment,
            'note' => null,
            'confirmed_at' => now(),
        ];
    }
}
