<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\{PaymentRunKind, PaymentRunStatus};
use App\Models\Finance\{BankAccount, PaymentRun};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRun>
 */
class PaymentRunFactory extends Factory {
    protected $model = PaymentRun::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'bank_account_id' => BankAccount::factory(),
            'kind' => PaymentRunKind::CreditTransfer,
            'status' => PaymentRunStatus::Draft,
            'execution_date' => now()->addDay()->toDateString(),
            'currency' => 'EUR',
            'total' => '0.00',
        ];
    }
}
