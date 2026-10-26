<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalTenancyFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Rental;

use App\Enums\Rental\RentalTenancyStatus;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Rental\RentalTenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Wohnung und Mieter entstehen in der Organisation des Mietverhältnisses.
 *
 * @extends Factory<RentalTenancy>
 */
class RentalTenancyFactory extends Factory {
    protected $model = RentalTenancy::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'asset_id' => fn (array $attributes) => Asset::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'status' => RentalTenancyStatus::Active,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => null,
            'rent_amount' => '850.00',
            'ancillary_amount' => '180.00',
            'currency' => 'EUR',
        ];
    }

    public function requested(): static {
        return $this->state(['status' => RentalTenancyStatus::Requested, 'rent_amount' => null, 'ancillary_amount' => null]);
    }
}
