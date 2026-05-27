<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Software\{SoftwareKind, SoftwareLicenseType};
use App\Models\{Organization, Software};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Software> */
class SoftwareFactory extends Factory {
    protected $model = Software::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'vendor' => fake()->company(),
            'kind' => SoftwareKind::Application->value,
            'license_type' => SoftwareLicenseType::Subscription->value,
            'default_version' => fake()->numerify('##.#'),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function operatingSystem(): static {
        return $this->state(fn(): array => [
            'kind' => SoftwareKind::OperatingSystem->value,
            'license_type' => SoftwareLicenseType::Oem->value,
        ]);
    }
}
