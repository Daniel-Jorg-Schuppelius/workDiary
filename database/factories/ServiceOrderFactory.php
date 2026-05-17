<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceOrderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    protected $model = ServiceOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'customer_id' => null,
            'project_id' => null,
            'assigned_user_id' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'address_line' => fake()->streetAddress(),
            'address_zip' => fake()->postcode(),
            'address_city' => fake()->city(),
            'address_country' => 'DE',
            'address_lat' => fake()->latitude(50, 53),
            'address_lng' => fake()->longitude(7, 14),
            'scheduled_for' => fake()->dateTimeBetween('-1 week', '+2 weeks')->format('Y-m-d'),
            'time_window_start' => null,
            'time_window_end' => null,
            'service_minutes' => 60,
            'priority' => ServiceOrder::PRIORITY_NORMAL,
            'status' => ServiceOrder::STATUS_PLANNED,
            'tour_id' => null,
            'tour_position' => null,
            'notes' => null,
        ];
    }

    public function done(): self
    {
        return $this->state(fn () => ['status' => ServiceOrder::STATUS_DONE]);
    }

    public function urgent(): self
    {
        return $this->state(fn () => ['priority' => ServiceOrder::PRIORITY_URGENT]);
    }
}
