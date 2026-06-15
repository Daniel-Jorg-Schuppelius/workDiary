<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankStatementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\{BalanceCheck, BankStatementFormat};
use App\Models\Finance\BankStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory {
    protected $model = BankStatement::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'bank_account_id' => null,
            'source_format' => BankStatementFormat::Camt053,
            'file_path' => 'imports/bank/test/' . $this->faker->uuid() . '.xml',
            'file_hash' => hash('sha256', $this->faker->uuid()),
            'statement_iban_hash' => hash('sha256', 'DE' . $this->faker->numerify('####################')),
            'opening_balance' => '1000.00',
            'closing_balance' => '1500.00',
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'tx_count' => 0,
            'balance_check' => BalanceCheck::Unknown,
        ];
    }
}
