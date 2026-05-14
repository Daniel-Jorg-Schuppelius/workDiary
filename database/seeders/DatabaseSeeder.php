<?php

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
