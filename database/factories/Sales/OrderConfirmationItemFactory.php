<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Sales;

use App\Models\Sales\{OrderConfirmation, OrderConfirmationItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderConfirmationItem>
 */
class OrderConfirmationItemFactory extends Factory {
    protected $model = OrderConfirmationItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'order_confirmation_id' => OrderConfirmation::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'quantity' => '1.0000',
            'unit' => 'Std.',
            'unit_price' => '95.00',
            'tax_rate' => '19.00',
        ];
    }
}
