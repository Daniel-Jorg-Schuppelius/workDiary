<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankTransactionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\{MatchStatus, TransactionDirection};
use App\Models\Finance\{BankStatement, BankTransaction};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory {
    protected $model = BankTransaction::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $amount = $this->faker->randomFloat(2, 10, 5000);

        return [
            'bank_statement_id' => BankStatement::factory(),
            'line_index' => $this->faker->numberBetween(0, 50),
            'booking_date' => now()->toDateString(),
            'valuta_date' => now()->toDateString(),
            'amount' => (string) $amount,
            'direction' => TransactionDirection::Credit,
            'currency' => 'EUR',
            'end_to_end_id' => null,
            'mandate_ref' => null,
            'counterparty_name' => $this->faker->company(),
            'counterparty_iban' => null,
            'counterparty_iban_hash' => null,
            'purpose' => $this->faker->sentence(),
            'extracted_refs' => [],
            'is_reversal' => false,
            'fingerprint' => hash('sha256', $this->faker->uuid()),
            'match_status' => MatchStatus::Unmatched,
        ];
    }
}
