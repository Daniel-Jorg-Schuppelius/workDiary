<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingSourceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Models\Finance\{DatevBookingBatch, DatevBookingSource};
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatevBookingSource>
 */
class DatevBookingSourceFactory extends Factory {
    protected $model = DatevBookingSource::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        // Quelle wird von den Tests explizit gesetzt. Default = Platzhalter
        // (Muster PaymentAllocationFactory).
        return [
            'datev_booking_batch_id' => DatevBookingBatch::factory(),
            'source_type' => Invoice::class,
            'source_id' => 1,
            'debtor_account' => '10001',
            'revenue_account' => '8400',
            'soll_haben' => 'S',
            'amount' => '119.00',
            'tax_key' => null,
            'document_ref' => 'RE-' . $this->faker->unique()->numerify('######'),
        ];
    }
}
