<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineProfileFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Customer;

use App\Models\Customer\{Customer, CustomerDeadlineProfile};
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fristmerkmale ohne Werte für den Regelsatz Steuerberatung, gültig ab
 * Jahresbeginn; Kunde erfunden.
 *
 * @extends Factory<CustomerDeadlineProfile>
 */
class CustomerDeadlineProfileFactory extends Factory {
    protected $model = CustomerDeadlineProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => static fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'rule_set' => 'tax_advisory',
            'traits' => null,
            'valid_from' => now()->startOfYear(),
            'valid_until' => null,
            'responsible_user_id' => null,
        ];
    }
}
