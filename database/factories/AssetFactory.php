<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Asset\{AssetClass, AssetHealth, AssetOwnership, AssetStatus};
use App\Models\{Asset, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory {
    protected $model = Asset::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'asset_no' => 'AS-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'asset_class' => AssetClass::Device->value,
            'category_code' => null,
            'name' => fake()->words(2, true),
            'manufacturer' => fake()->company(),
            'model' => strtoupper(fake()->bothify('??-###')),
            'serial_no' => strtoupper(fake()->bothify('SN-#####')),
            'inventory_no' => strtoupper(fake()->bothify('INV-#####')),
            'customer_id' => null,
            'owned_by' => AssetOwnership::Organization->value,
            'location_text' => fake()->city(),
            'location_lat' => null,
            'location_lng' => null,
            'status' => AssetStatus::Active->value,
            'health' => AssetHealth::Ok->value,
            'commissioned_on' => now()->toDateString(),
            'decommissioned_on' => null,
            'warranty_until' => now()->addYear()->toDateString(),
            'next_maintenance_on' => null,
            'next_inspection_on' => null,
            'notes' => null,
            'custom' => null,
        ];
    }
}
