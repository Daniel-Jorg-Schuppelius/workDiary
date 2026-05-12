<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory {
    protected $model = Organization::class;

    public function definition(): array {
        $name = fake()->company();

        return [
            'name'     => $name,
            'slug'     => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'plan'     => Organization::PLAN_FREE,
            'locale'   => 'de',
            'timezone' => 'Europe/Berlin',
            'settings' => null,
            'is_active' => true,
        ];
    }

    public function pro(): static {
        return $this->state(['plan' => Organization::PLAN_PRO]);
    }

    public function enterprise(): static {
        return $this->state(['plan' => Organization::PLAN_ENTERPRISE]);
    }

    public function inactive(): static {
        return $this->state(['is_active' => false]);
    }
}
