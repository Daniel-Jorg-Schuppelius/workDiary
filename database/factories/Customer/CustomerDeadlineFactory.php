<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Customer;

use App\Enums\Customer\CustomerDeadlineStatus;
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Offene USt-Voranmeldung des Vormonats, fällig in einer Woche; Kunde erfunden.
 *
 * @extends Factory<CustomerDeadline>
 */
class CustomerDeadlineFactory extends Factory {
    protected $model = CustomerDeadline::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $due = now()->addWeek()->startOfDay();

        return [
            'organization_id' => Organization::factory(),
            'customer_id' => static fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'rule_set' => 'tax_advisory',
            'kind' => 'vat_advance',
            'period_key' => now()->subMonthNoOverflow()->format('Y-m'),
            'due_on' => $due,
            'original_due_on' => $due,
            'status' => CustomerDeadlineStatus::Open,
        ];
    }
}
