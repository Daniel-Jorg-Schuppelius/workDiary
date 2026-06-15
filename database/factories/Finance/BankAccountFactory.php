<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankAccountFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory {
    protected $model = BankAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $iban = 'DE' . $this->faker->numerify('################') . $this->faker->numerify('####');

        return [
            'label' => $this->faker->company() . ' Geschäftskonto',
            'iban' => substr($iban, 0, 22),
            'bic' => 'COBADEFFXXX',
            'account_holder' => $this->faker->company(),
            'datev_account_no' => (string) $this->faker->numberBetween(1200, 1299),
            'is_active' => true,
        ];
    }
}
