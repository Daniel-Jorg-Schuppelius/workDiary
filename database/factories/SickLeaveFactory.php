<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeaveFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\SickLeave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\Sickness\SickLeaveKind;

/**
 * @extends Factory<SickLeave>
 */
class SickLeaveFactory extends Factory
{
    protected $model = SickLeave::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', 'now');
        $end = (clone $start)->modify('+'.fake()->numberBetween(0, 6).' days');

        return [
            'user_id' => User::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'kind' => SickLeaveKind::Initial->value,
            'follow_up_for_id' => null,
            'au_number' => null,
            'doctor_name' => null,
            'note' => null,
            'kasse_notified_at' => null,
            'reported_at' => now(),
            'recorded_by' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    public function followUp(SickLeave $previous): self
    {
        return $this->state(function () use ($previous): array {
            $start = $previous->end_date->copy()->addDay();
            $end = $start->copy()->addDays(fake()->numberBetween(2, 10));

            return [
                'user_id' => $previous->user_id,
                'kind' => SickLeaveKind::FollowUp->value,
                'follow_up_for_id' => $previous->id,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ];
        });
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
            'cancel_reason' => 'Stornierung im Test',
        ]);
    }
}
