<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SiteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Facility;

use App\Models\Customer\Customer;
use App\Models\Facility\Site;
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory {
    protected $model = Site::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Customer::factory(),
            'name' => fake()->randomElement(['Hauptstandort', 'Niederlassung', 'Werk', 'Filiale'])
                . ' ' . fake()->city(),
            'code' => strtoupper(fake()->unique()->bothify('S-###')),
            'address_street' => fake()->streetAddress(),
            'address_zip' => fake()->postcode(),
            'address_city' => fake()->city(),
            'country' => 'DE',
            'geo_lat' => null,
            'geo_lng' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): self {
        return $this->state(fn(): array => ['is_active' => false]);
    }
}
