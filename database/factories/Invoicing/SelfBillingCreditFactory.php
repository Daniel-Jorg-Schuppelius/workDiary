<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfBillingCreditFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Invoicing;

use App\Models\Invoicing\SelfBillingCredit;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelfBillingCredit>
 */
class SelfBillingCreditFactory extends Factory {
    protected $model = SelfBillingCredit::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'supplier_id' => Supplier::factory(),
            'status' => 'draft',
            'currency' => 'EUR',
            'service_from' => now()->startOfMonth()->toDateString(),
            'service_until' => now()->endOfMonth()->toDateString(),
        ];
    }
}
