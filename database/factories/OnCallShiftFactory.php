<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnCallShift>
 */
class OnCallShiftFactory extends Factory
{
    protected $model = OnCallShift::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');
        $end = (clone $start)->modify('+8 hours');

        return [
            'user_id' => User::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'note' => null,
            'is_archived' => false,
        ];
    }
}
