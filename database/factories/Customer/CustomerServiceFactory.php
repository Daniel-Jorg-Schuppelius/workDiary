<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServiceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Customer;

use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pflichtleistung seit Jahresbeginn, jährlich; Kunde und Eintragsart erfunden.
 *
 * @extends Factory<CustomerService>
 */
class CustomerServiceFactory extends Factory {
    protected $model = CustomerService::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => static fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'entry_type_id' => static fn (array $attributes) => EntryType::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'product_group_code' => null,
            'is_mandatory' => true,
            'period_kind' => PeriodKind::Year,
            'valid_from' => now()->startOfYear(),
            'valid_until' => null,
        ];
    }
}
