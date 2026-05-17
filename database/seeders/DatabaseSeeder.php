<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatabaseSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(OrganizationSeeder::class);
        $this->call(MaterialSeeder::class);
        $this->call(ActivityCategorySeeder::class);

        $org = Organization::where('slug', 'default')->first();

        User::factory()->admin()->create([
            'name' => 'Administrator',
            'email' => 'admin@workdiary.local',
            'password' => Hash::make('admin'),
            'organization_id' => $org?->id,
        ]);

        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'organization_id' => $org?->id,
        ]);
    }
}
