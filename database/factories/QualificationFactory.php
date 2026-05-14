<?php

namespace Database\Factories;

use App\Models\Qualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Qualification>
 */
class QualificationFactory extends Factory
{
    protected $model = Qualification::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'abbreviation' => strtoupper($this->faker->lexify('???')),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
