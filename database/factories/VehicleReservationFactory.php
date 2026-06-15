<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{User, Vehicle, VehicleReservation};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<VehicleReservation>
 */
class VehicleReservationFactory extends Factory {
    protected $model = VehicleReservation::class;

    public function definition(): array {
        $from = Carbon::tomorrow()->setTime(8, 0);

        return [
            'organization_id' => null,
            'vehicle_id' => Vehicle::factory(),
            'diary_entry_id' => null,
            'reserved_by_user_id' => User::factory(),
            'reserved_from' => $from,
            'reserved_to' => (clone $from)->addHours(4),
            'note' => null,
        ];
    }

    /**
     * @param array{0: \DateTimeInterface|string, 1: \DateTimeInterface|string} $window
     */
    public function window(\DateTimeInterface|string $from, \DateTimeInterface|string $to): self {
        return $this->state(fn() => [
            'reserved_from' => $from,
            'reserved_to' => $to,
        ]);
    }
}
