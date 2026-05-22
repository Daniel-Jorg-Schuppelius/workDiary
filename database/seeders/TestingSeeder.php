<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TestingSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\User\Permission as PermissionEnum;
use App\Enums\User\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder, der ausschließlich in der Test-Suite gefahren wird (über
 * RefreshDatabase + $seed=true in Tests\TestCase). Legt einmalig pro
 * Test-Prozess die globalen 'web'-Permissions und Default-Rollen an,
 * damit setUp()-Pfade nicht je Testmethode RolesSeeder/PermissionsSeeder
 * neu fahren müssen. Läuft außerhalb der Test-Transaktion und bleibt
 * über alle Tests hinweg persistent.
 */
class TestingSeeder extends Seeder {
    public function run(): void {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (UserRole::values() as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
