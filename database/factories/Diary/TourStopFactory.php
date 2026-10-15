<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourStopFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Diary;

use App\Models\Diary\TourStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourStop>
 */
class TourStopFactory extends Factory {
    protected $model = TourStop::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'tour_id' => null,
            'diary_entry_id' => null,
            'position' => 1,
            'planned_arrival_at' => null,
            'planned_departure_at' => null,
            'waiting_minutes' => 0,
        ];
    }
}
