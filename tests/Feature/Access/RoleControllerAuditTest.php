<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoleControllerAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Access;

use App\Models\{AuditLog, User};
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Akzeptanzkriterium MVP-003: "Rechteänderungen werden protokolliert".
 * Stellt sicher, dass Anlegen, Aktualisieren und Löschen einer Rolle
 * je einen `role.*`-AuditLog-Eintrag in der aktuellen Organisation
 * erzeugt.
 */
class RoleControllerAuditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->admin = User::factory()
            ->state(['organization_id' => $this->organization->id])
            ->admin()
            ->create();
    }

    public function test_create_update_and_delete_role_are_audited(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.access.roles.store'), [
                'name' => 'custom-role',
                'permissions' => ['customer.viewAny'],
            ])
            ->assertRedirect(route('admin.access.roles.index'));

        $role = Role::where('name', 'custom-role')
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'event' => 'role.created',
            'auditable_type' => Role::class,
            'auditable_id' => $role->getKey(),
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.access.roles.update', $role), [
                'permissions' => ['customer.viewAny', 'customer.view'],
            ])
            ->assertRedirect(route('admin.access.roles.index'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'role.updated',
            'auditable_id' => $role->getKey(),
        ]);

        $roleId = $role->getKey();
        $this->actingAs($this->admin)
            ->delete(route('admin.access.roles.destroy', $role))
            ->assertRedirect(route('admin.access.roles.index'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'role.deleted',
            'auditable_id' => $roleId,
        ]);

        // Insgesamt mindestens 3 role.*-Einträge — Reihenfolge stabilitätshalber per filter.
        $this->assertSame(
            3,
            AuditLog::query()
                ->where('organization_id', $this->organization->id)
                ->where('event', 'like', 'role.%')
                ->count(),
        );
    }

    public function test_meldestelle_role_is_system_protected_and_cannot_be_deleted(): void {
        WhistleblowingPermissions::seedOrganization($this->organization);
        $role = Role::where('name', WhistleblowingPermissions::ROLE_MELDESTELLE)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.access.roles.destroy', $role));

        $this->assertDatabaseHas('roles', ['id' => $role->getKey()]);
        $this->assertDatabaseMissing('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'role.deleted',
            'auditable_id' => $role->getKey(),
        ]);
    }
}
