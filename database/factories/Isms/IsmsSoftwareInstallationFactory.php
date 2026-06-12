<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareInstallationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Models\Isms\{IsmsSoftwareInstallation, IsmsSoftwareProduct};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsSoftwareInstallation>
 */
class IsmsSoftwareInstallationFactory extends Factory {
    protected $model = IsmsSoftwareInstallation::class;

    public function definition(): array {
        return [
            'isms_software_product_id' => IsmsSoftwareProduct::factory(),
            'installed_version' => fake()->semver(),
            'asset_ref' => 'Server ' . fake()->word(),
            'location' => fake()->city(),
            'notes' => null,
        ];
    }
}
