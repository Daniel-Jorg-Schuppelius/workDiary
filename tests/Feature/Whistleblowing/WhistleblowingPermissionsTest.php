<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingPermissionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Enums\User\Permission as CentralPermission;
use App\Models\Organization;
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kerninvariante des Hinweisgebersystems (HinSchG): die Fall-Permissions sind
 * BEWUSST von der zentralen Admin-Enum getrennt — der Plattform-Admin (der per
 * Seeder alle Enum-Permissions erhält) bekommt damit NICHT automatisch Zugriff
 * auf Meldeinhalte. Zugriff läuft ausschließlich über die Rolle `meldestelle`.
 */
final class WhistleblowingPermissionsTest extends TestCase {
    use RefreshDatabase;

    private function teamForeign(): string {
        return (string) config('permission.column_names.team_foreign_key', 'team_id');
    }

    public function test_all_permissions_are_created_idempotently(): void {
        WhistleblowingPermissions::ensurePermissionsExist();
        WhistleblowingPermissions::ensurePermissionsExist(); // idempotent

        foreach (WhistleblowingPermissions::ALL as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_seed_organization_creates_meldestelle_role_with_all_permissions(): void {
        $org = Organization::factory()->create();
        WhistleblowingPermissions::seedOrganization($org);

        $role = Role::query()
            ->where($this->teamForeign(), $org->id)
            ->where('name', WhistleblowingPermissions::ROLE_MELDESTELLE)
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull($role, 'Rolle meldestelle muss für die Organisation existieren');
        $granted = $role->permissions->pluck('name')->all();
        foreach (WhistleblowingPermissions::ALL as $name) {
            $this->assertContains($name, $granted, "Rolle meldestelle muss $name tragen");
        }
    }

    public function test_seed_organization_is_idempotent(): void {
        $org = Organization::factory()->create();
        WhistleblowingPermissions::seedOrganization($org);
        WhistleblowingPermissions::seedOrganization($org);

        $count = Role::query()
            ->where($this->teamForeign(), $org->id)
            ->where('name', WhistleblowingPermissions::ROLE_MELDESTELLE)
            ->count();

        $this->assertSame(1, $count, 'Wiederholtes Seeding darf keine Duplikate erzeugen');
    }

    public function test_permissions_are_disjoint_from_central_admin_enum(): void {
        // Die strukturelle Garantie der Trennung: keine Hinweisgeber-Permission
        // taucht in der zentralen Enum auf (sonst erhielte der Admin sie automatisch).
        $centralValues = array_map(static fn(CentralPermission $c): string => $c->value, CentralPermission::cases());

        foreach (WhistleblowingPermissions::ALL as $name) {
            $this->assertNotContains(
                $name,
                $centralValues,
                "Hinweisgeber-Permission $name darf NICHT in der zentralen Admin-Enum stehen.",
            );
        }
    }
}
