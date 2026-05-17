<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-7 days', 'now'));
        $end = (clone $start)->addMinutes(fake()->numberBetween(60, 480));

        return [
            'organization_id' => null,
            'user_id' => User::factory(),
            'started_at' => $start,
            'ended_at' => $end,
            'date' => $start->copy()->startOfDay(),
            'break_minutes_auto' => 0,
            'break_minutes_manual' => 0,
            'duration_minutes' => 0, // recalculated in saving()
            'source' => Attendance::SOURCE_CLOCK,
            'status' => Attendance::STATUS_CLOSED,
            'note' => null,
        ];
    }

    public function open(): self
    {
        return $this->state(fn () => [
            'ended_at' => null,
            'status' => Attendance::STATUS_OPEN,
        ]);
    }
}
