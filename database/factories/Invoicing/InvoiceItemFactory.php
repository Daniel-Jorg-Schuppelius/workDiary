<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Invoicing;

use App\Models\Invoicing\{Invoice, InvoiceItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory {
    protected $model = InvoiceItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'invoice_id' => Invoice::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'quantity' => '1.000',
            'unit' => 'h',
            'unit_price' => '95.0000',
            'tax_rate' => '19.00',
        ];
    }
}
