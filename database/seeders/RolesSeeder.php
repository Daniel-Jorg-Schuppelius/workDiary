<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RolesSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\User\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder {
    public function run(): void {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([UserRole::Admin->value, UserRole::User->value, UserRole::Callcenter->value, UserRole::Buchhaltung->value] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
