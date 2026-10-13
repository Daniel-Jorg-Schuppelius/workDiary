<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Sales;

use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Customer\Customer;
use App\Models\Sales\OrderConfirmation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderConfirmation>
 */
class OrderConfirmationFactory extends Factory {
    protected $model = OrderConfirmation::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'customer_id' => Customer::factory(),
            'number' => 'AB-' . fake()->unique()->numerify('######'),
            'status' => OrderConfirmationStatus::Draft,
            'currency' => 'EUR',
            'created_by' => null,
        ];
    }
}
