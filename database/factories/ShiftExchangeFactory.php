<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Shift\ShiftExchangeStatus;
use App\Models\{ScheduledShift, ShiftExchange, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftExchange>
 */
class ShiftExchangeFactory extends Factory {
    protected $model = ShiftExchange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'scheduled_shift_id' => ScheduledShift::factory(),
            'requested_by_user_id' => User::factory(),
            'target_user_id' => null,
            'offered_shift_id' => null,
            'status' => ShiftExchangeStatus::Requested,
            'decided_by_user_id' => null,
            'decided_at' => null,
            'reason' => null,
        ];
    }

    public function accepted(): static {
        return $this->state(['status' => ShiftExchangeStatus::Accepted]);
    }

    public function approved(): static {
        return $this->state(['status' => ShiftExchangeStatus::Approved]);
    }

    public function rejected(): static {
        return $this->state(['status' => ShiftExchangeStatus::Rejected]);
    }
}
