<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetiredPermissionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\Platform\Organization;
use App\Services\Org\OrganizationRoles;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Ausgemusterte Rechte verschwinden beim Seeden samt Zuordnungen (Vollscan 2026-10-21). */
class RetiredPermissionsTest extends TestCase {
    use RefreshDatabase;

    public function test_seeder_removes_retired_permissions_and_their_assignments(): void {
        $org = Organization::factory()->create();
        $retired = Permission::query()->create(['name' => 'shift.manage', 'guard_name' => 'web']);
        $role = OrganizationRoles::named('schichtplanung', $org->id);
        $role->givePermissionTo($retired);

        $this->seed(PermissionsSeeder::class);

        $this->assertFalse(Permission::query()->where('name', 'shift.manage')->exists());
        $this->assertFalse(DB::table('role_has_permissions')->where('permission_id', $retired->id)->exists());
        $this->assertTrue(Permission::query()->where('name', 'scheduled-shift.manage')->exists());
    }
}
