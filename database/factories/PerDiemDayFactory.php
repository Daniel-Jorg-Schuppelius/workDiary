<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemDayFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Expense\PerDiemDayKind;
use App\Models\PerDiemDay;
use App\Models\PerDiemTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerDiemDay>
 */
class PerDiemDayFactory extends Factory {
    protected $model = PerDiemDay::class;

    public function definition(): array {
        return [
            'per_diem_trip_id' => PerDiemTrip::factory(),
            'date' => now()->toDateString(),
            'kind' => PerDiemDayKind::FullDay,
            'country' => 'DE',
            'per_diem_rate_id' => null,
            'base_amount' => '28.00',
            'deduction_breakfast' => '0.00',
            'deduction_lunch' => '0.00',
            'deduction_dinner' => '0.00',
            'deductions_total' => '0.00',
            'amount' => '28.00',
            'meal_breakfast' => false,
            'meal_lunch' => false,
            'meal_dinner' => false,
            'currency' => 'EUR',
            'notes' => null,
        ];
    }
}
