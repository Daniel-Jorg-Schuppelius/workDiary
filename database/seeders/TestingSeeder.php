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

use App\Enums\User\{Permission as PermissionEnum, UserRole};
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Permission, Role};
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
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // Bulk statt findOrCreate je Permission (je Aufruf ein kompletter
        // Spatie-Cache-Neuaufbau — bei 465 Permissions Minutenkosten).
        $enumValues = array_map(static fn(PermissionEnum $p): string => $p->value, PermissionEnum::cases());
        $existing = Permission::query()->where('guard_name', 'web')->whereIn('name', $enumValues)->pluck('name')->all();
        $now = now();
        $missing = array_diff($enumValues, $existing);
        if ($missing !== []) {
            Permission::query()->insert(array_map(static fn(string $name): array => [
                'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ], array_values($missing)));
        }

        // Die bewusst getrennten Permission-Kataloge (Meldestelle/Datenschutz)
        // ebenfalls EINMAL pro Prozess anlegen - sonst legte jede Org-Anlage
        // sie in der Test-Transaktion neu an (und das Rollback warf sie weg):
        // ~2,2 s je Testmethode (Messung 2026-08-19).
        \App\Services\Whistleblowing\WhistleblowingPermissions::ensurePermissionsExist();
        \App\Services\Privacy\DataProtectionPermissions::ensurePermissionsExist();
        \App\Services\Hr\PersonnelFilePermissions::ensurePermissionsExist();

        $registrar->setPermissionsTeamId(null);
        foreach (UserRole::values() as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Global-Admin (team_id NULL) bekommt wie im PermissionsSeeder alle
        // Enum-Permissions zugeordnet — einmal pro Prozess statt (wie früher
        // über seed(PermissionsSeeder) in setUp()) je Testmethode. Die
        // org-spezifischen Rollen-Matrizen entstehen über den
        // OrganizationObserver bei jeder Org-Anlage automatisch.
        //
        // **Muss dem PermissionsSeeder folgen** — sonst prüfen die Tests eine
        // andere Rechtelage als die Produktion.
        /** @var Role $globalAdmin */
        $globalAdmin = Role::findOrCreate(UserRole::Admin->value, 'web');
        $enumNames = array_map(static fn(PermissionEnum $p): string => $p->value, PermissionEnum::cases());
        $globalAdmin->syncPermissions(
            Permission::query()->where('guard_name', 'web')->whereIn('name', $enumNames)->get(),
        );
        $registrar->forgetCachedPermissions();
    }
}
