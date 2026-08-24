<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingEventFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{DatevBookingBatch, DatevBookingEvent};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatevBookingEvent>
 *
 * prev_hash/hash setzt der HashChained-Trait im creating-Event selbst —
 * die Factory liefert nur die fachlichen Felder (Rohwerte im Payload!).
 */
class DatevBookingEventFactory extends Factory {
    protected $model = DatevBookingEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'datev_booking_batch_id' => DatevBookingBatch::factory(),
            // Kette ist scope-frei — die Org kommt aus dem Batch, nicht aus dem Binding.
            'organization_id' => fn (array $attributes): ?int => DatevBookingBatch::withoutGlobalScopes()
                ->find($attributes['datev_booking_batch_id'])?->organization_id,
            'event' => 'created',
            'payload' => ['booking_count' => 0],
        ];
    }
}
