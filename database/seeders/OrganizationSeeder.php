<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Organisation',
                'plan' => Organization::PLAN_FREE,
                'locale' => 'de',
                'timezone' => 'Europe/Berlin',
                'is_active' => true,
            ]
        );

        // Alle User ohne Organization der Default-Org zuweisen
        User::whereNull('organization_id')->update(['organization_id' => $org->id]);
    }
}
