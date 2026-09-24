<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Gaeb;

use App\Enums\Gaeb\BoqItemType;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqItem>
 */
class BoqItemFactory extends Factory {
    protected $model = BoqItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'bill_of_quantity_id' => BillOfQuantity::factory(),
            'reference_no' => '01.' . fake()->unique()->numerify('####'),
            'type' => BoqItemType::Standard->value,
            'short_text' => fake()->sentence(3),
            'quantity' => '1.0000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
            'vat_rate' => '19.00',
            'currency' => 'EUR',
            'position' => 1,
        ];
    }
}
