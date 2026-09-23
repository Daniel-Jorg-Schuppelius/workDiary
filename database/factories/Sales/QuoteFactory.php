<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Customer, Quote};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory {
    protected $model = Quote::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'customer_id' => Customer::factory(),
            'number' => 'A-' . fake()->unique()->numerify('######'),
            'version' => 1,
            'status' => 'draft',
            'valid_until' => now()->addDays(30)->toDateString(),
            'created_by' => null,
        ];
    }
}
