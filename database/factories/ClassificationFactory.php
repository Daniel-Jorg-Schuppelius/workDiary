<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classification>
 */
class ClassificationFactory extends Factory {
    protected $model = Classification::class;

    public function definition(): array {
        $code = $this->faker->unique()->lexify('code_??????');

        return [
            'organization_id' => null,
            'domain' => ClassificationDomain::EntryType->value,
            'code' => $code,
            'label' => ucfirst($code),
            'label_i18n' => null,
            'sort_order' => 100,
            'color_hex' => null,
            'icon' => null,
            'active' => true,
            'deprecated_at' => null,
            'description' => null,
        ];
    }

    public function platformDefault(): static {
        return $this->state(['organization_id' => null]);
    }

    public function forOrganization(int $organizationId): static {
        return $this->state(['organization_id' => $organizationId]);
    }

    public function domain(ClassificationDomain $domain): static {
        return $this->state(['domain' => $domain->value]);
    }

    public function inactive(): static {
        return $this->state(['active' => false, 'deprecated_at' => now()]);
    }
}
