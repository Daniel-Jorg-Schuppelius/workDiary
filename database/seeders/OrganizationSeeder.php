<?php

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
