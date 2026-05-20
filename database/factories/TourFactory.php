<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Tour\TourStatus;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'user_id' => null,
            'vehicle_id' => null,
            'tour_date' => now()->toDateString(),
            'name' => null,
            'start_address' => null,
            'start_lat' => null,
            'start_lng' => null,
            'end_address' => null,
            'end_lat' => null,
            'end_lng' => null,
            'planned_distance_km' => 0,
            'planned_duration_minutes' => 0,
            'route_geometry' => null,
            'status' => TourStatus::Draft,
            'notes' => null,
        ];
    }

    public function planned(): self
    {
        return $this->state(fn () => ['status' => TourStatus::Planned]);
    }
}
