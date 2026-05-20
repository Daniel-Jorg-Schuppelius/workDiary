<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder {
    public function run(): void {
        $org = Organization::where('slug', 'default')->first();
        if (! $org) {
            return;
        }

        $items = [
            ['sku' => 'KAB-CAT6-1M', 'name' => 'Patchkabel Cat.6, 1 m',          'unit' => 'Stk', 'default_unit_price' => 3.50,  'tax_rate' => 19.00],
            ['sku' => 'KAB-CAT6-3M', 'name' => 'Patchkabel Cat.6, 3 m',          'unit' => 'Stk', 'default_unit_price' => 5.90,  'tax_rate' => 19.00],
            ['sku' => 'STD-DOSE-2',  'name' => 'Netzwerkdose 2-fach Cat.6a',     'unit' => 'Stk', 'default_unit_price' => 12.50, 'tax_rate' => 19.00],
            ['sku' => 'WLAN-AP-22',  'name' => 'WLAN Access-Point Wi-Fi 6',      'unit' => 'Stk', 'default_unit_price' => 189.00, 'tax_rate' => 19.00],
            ['sku' => 'KM-FAHRT',    'name' => 'Fahrtkilometer',                 'unit' => 'km',  'default_unit_price' => 0.42,  'tax_rate' => 19.00],
            ['sku' => 'PAUSCH-ANF',  'name' => 'Anfahrtspauschale',              'unit' => 'Pos', 'default_unit_price' => 25.00, 'tax_rate' => 19.00],
        ];

        foreach ($items as $i) {
            Material::updateOrCreate(
                ['organization_id' => $org->id, 'sku' => $i['sku']],
                $i + ['organization_id' => $org->id, 'is_active' => true],
            );
        }
    }
}
