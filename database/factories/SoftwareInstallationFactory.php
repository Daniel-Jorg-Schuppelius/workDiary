<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInstallationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Asset, Organization, Software, SoftwareInstallation};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SoftwareInstallation> */
class SoftwareInstallationFactory extends Factory {
    protected $model = SoftwareInstallation::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_id' => Asset::factory(),
            'software_id' => Software::factory(),
            'version' => fake()->numerify('##.#.#'),
            'license_key' => null,
            'seats' => 1,
            'installed_on' => null,
            'expires_on' => null,
            'is_operating_system' => false,
            'notes' => null,
        ];
    }

    public function operatingSystem(): static {
        return $this->state(fn(): array => ['is_operating_system' => true]);
    }
}
