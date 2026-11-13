<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServicePeriodNoteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\ServicePlan;

use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\ServicePlan\CustomerServicePeriodNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unterlagen da und Bemerkung für das laufende Jahr, erfundener Text.
 *
 * @extends Factory<CustomerServicePeriodNote>
 */
class CustomerServicePeriodNoteFactory extends Factory {
    protected $model = CustomerServicePeriodNote::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => static fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'entry_type_id' => static fn (array $attributes) => EntryType::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'product_group_code' => null,
            'period_key' => (string) now()->year,
            'documents_received_on' => now()->toDateString(),
            'note' => 'Belege vollständig',
        ];
    }
}
