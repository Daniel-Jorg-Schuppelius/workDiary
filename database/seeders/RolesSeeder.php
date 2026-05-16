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

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder {
    public function run(): void {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([User::ROLE_ADMIN, User::ROLE_USER, User::ROLE_CALLCENTER, User::ROLE_BUCHHALTUNG] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
