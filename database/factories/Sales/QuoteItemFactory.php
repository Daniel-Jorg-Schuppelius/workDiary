<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Quote, QuoteItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory {
    protected $model = QuoteItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'quote_id' => Quote::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'quantity' => '1.00',
            'unit' => 'Std.',
            'unit_price' => '95.00',
            'tax_rate' => '19.00',
            'optional' => false,
        ];
    }
}
