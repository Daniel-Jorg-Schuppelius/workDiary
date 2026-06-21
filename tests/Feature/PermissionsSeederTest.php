<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermissionsSeederTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\Permission as PermissionEnum;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Schützt den Permission-Seeder gegen die Klasse von Fehlern, die auf Prod
 * (frischer Enum, alte DB) als `PermissionDoesNotExist` beim Seeding aufschlug:
 * Eine neue Permission (z. B. `article.view`) fehlt, während die GESAMTZAHL der
 * Permissions zufällig stimmt. Der frühere Count-basierte Fast-Path übersah das.
 */
class PermissionsSeederTest extends TestCase {
    use RefreshDatabase;

    public function test_seeding_creates_every_enum_permission(): void {
        $this->invokeEnsure();

        foreach (PermissionEnum::cases() as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_missing_permission_is_created_even_when_total_count_matches(): void {
        $this->invokeEnsure(); // Baseline: alle Enum-Permissions existieren.

        $target = PermissionEnum::ArticleView->value;

        // Eine Enum-Permission entfernen ...
        Permission::query()->where('guard_name', 'web')->where('name', $target)->delete();

        // ... und die Gesamtzahl mit einer Fremd-Permission wieder auffüllen,
        // sodass ein reiner Count-Vergleich fälschlich "alles vorhanden" annähme.
        Permission::findOrCreate('zzz.regression.filler', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDatabaseMissing('permissions', ['name' => $target, 'guard_name' => 'web']);

        // Der namensbasierte Abgleich muss den fehlenden Namen trotzdem anlegen.
        $this->invokeEnsure();

        $this->assertDatabaseHas('permissions', ['name' => $target, 'guard_name' => 'web']);
    }

    private function invokeEnsure(): void {
        $method = new ReflectionMethod(PermissionsSeeder::class, 'ensurePermissionsExist');
        $method->setAccessible(true);
        $method->invoke(null);
    }
}
