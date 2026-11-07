<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Enums\Finance\{ExternalInvoiceStatus, ProviderSendState};
use App\Models\Customer\Customer;
use App\Models\Finance\ExternalInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExternalInvoice> */
class ExternalInvoiceFactory extends Factory {
    protected $model = ExternalInvoice::class;

    public function definition(): array {
        return [
            'customer_id' => Customer::factory(),
            'organization_id' => fn (array $attributes): int => (int) Customer::query()->withoutGlobalScopes()->whereKey($attributes['customer_id'])->value('organization_id'),
            'plugin_id' => 'lexoffice',
            'external_id' => $this->faker->uuid(),
            'external_number' => 'RE-' . $this->faker->unique()->numberBetween(1000, 99999),
            'status' => ExternalInvoiceStatus::Open,
            'kind' => ExternalInvoice::KIND_INVOICE,
            'issued_on' => now()->toDateString(),
            'currency' => 'EUR',
            'provider_send_state' => ProviderSendState::Unknown,
        ];
    }
}
