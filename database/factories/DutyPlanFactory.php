<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Shift\DutyPlanPeriodType;
use App\Enums\Shift\DutyPlanStatus;
use App\Models\DutyPlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutyPlan>
 */
class DutyPlanFactory extends Factory
{
    protected $model = DutyPlan::class;

    public function definition(): array
    {
        $from = fake()->dateTimeBetween('-1 month', '+1 month');
        $to = (clone $from)->modify('+6 days');

        return [
            'title' => fake()->sentence(3),
            'period_type' => fake()->randomElement(DutyPlanPeriodType::cases()),
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'status' => DutyPlanStatus::Draft,
            'min_staff' => 0,
            'note' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => DutyPlanStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(['status' => DutyPlanStatus::Published]);
    }

    public function weekly(): static
    {
        return $this->state(['period_type' => DutyPlanPeriodType::Weekly]);
    }

    public function monthly(): static
    {
        $from = fake()->dateTimeBetween('-1 month', '+1 month');
        $to = Carbon::parse($from)->endOfMonth();

        return $this->state([
            'period_type' => DutyPlanPeriodType::Monthly,
            'from_date' => $from->format('Y-m-01'),
            'to_date' => $to->format('Y-m-d'),
        ]);
    }
}
