<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\EntryType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntryType>
 */
class EntryTypeFactory extends Factory
{
    protected $model = EntryType::class;

    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'organization_id' => null,
            'slug' => Str::slug($label),
            'label' => ucfirst($label),
            'icon' => 'assignment',
            'color' => 'primary',
            'description' => null,
            'sort' => 0,
            'is_active' => true,
            'requires_customer' => false,
            'requires_address' => false,
            'requires_schedule' => false,
            'requires_tour' => false,
            'allow_priority' => true,
            'allow_tour' => false,
            'default_status' => 2,
            'default_service_minutes' => null,
            'default_priority' => null,
        ];
    }

    public function service(): self
    {
        return $this->state(fn () => [
            'slug' => EntryType::SLUG_SERVICE,
            'label' => 'Service-Auftrag',
            'icon' => 'home_repair_service',
            'color' => 'info',
            'requires_customer' => true,
            'requires_address' => true,
            'requires_schedule' => true,
            'requires_tour' => false,
            'allow_tour' => true,
            'default_service_minutes' => 60,
            'default_priority' => 'normal',
        ]);
    }
}
