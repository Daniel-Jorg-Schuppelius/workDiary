<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_CALLCENTER, User::ROLE_BUCHHALTUNG] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
