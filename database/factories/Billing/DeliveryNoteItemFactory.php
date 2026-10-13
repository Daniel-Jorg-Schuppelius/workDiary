<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Billing;

use App\Models\Billing\{DeliveryNote, DeliveryNoteItem};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNoteItem>
 */
class DeliveryNoteItemFactory extends Factory {
    protected $model = DeliveryNoteItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'delivery_note_id' => DeliveryNote::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'quantity' => '1.0000',
            'unit' => 'Stk',
        ];
    }
}
