<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingBatchFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Finance;

use App\Enums\Finance\{ChartOfAccounts, DatevBatchStatus};
use App\Models\Finance\DatevBookingBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatevBookingBatch>
 */
class DatevBookingBatchFactory extends Factory {
    protected $model = DatevBookingBatch::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'batch_no' => $this->faker->unique()->numberBetween(1, 99999),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'status' => DatevBatchStatus::Draft,
            'skr' => ChartOfAccounts::Skr03->value,
            'advisor_number' => 12345,
            'client_number' => 1,
            'booking_count' => 0,
            'total_amount' => '0.00',
            'finalized_locked' => false,
        ];
    }

    public function exported(): self {
        return $this->state(fn(): array => [
            'status' => DatevBatchStatus::Exported,
            'finalized_at' => now(),
            'file_path' => 'exports/finance/datev/test.csv',
            'file_hash' => hash('sha256', 'test'),
        ]);
    }
}
