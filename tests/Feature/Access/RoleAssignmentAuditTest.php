<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoleAssignmentAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Access;

use App\Enums\User\UserRole;
use App\Models\{AuditLog, User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bauturbo A17 (MVP-335): Rollen-/Permission-Vergabe an Usern und Gruppen
 * erzeugt die in supportzugriff-grundsaetze.md §4.1 geforderten Audit-Events
 * `user.role.assigned` / `user.role.revoked` / `user.permission.granted` /
 * `user.permission.revoked` — ausschließlich bei ECHTEN Änderungen
 * (Sync-Diff), No-Op-Syncs bleiben eventfrei.
 */
class RoleAssignmentAuditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_member_store_audits_role_assignment(): void {
        $this->actingAs($this->admin)
            ->post(route('org.members.store'), [
                'name' => 'Neues Mitglied',
                'email' => 'neu@example.test',
                'role' => UserRole::User->value,
                'password' => 'Sicher#Passwort9',
                'password_confirmation' => 'Sicher#Passwort9',
            ])
            ->assertRedirect(route('org.members.index'));

        $member = User::withoutGlobalScopes()->where('email', 'neu@example.test')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'event' => 'user.role.assigned',
            'auditable_type' => User::class,
            'auditable_id' => $member->id,
        ]);

        $log = AuditLog::query()
            ->where('event', 'user.role.assigned')
            ->where('auditable_id', $member->id)
            ->firstOrFail();
        $this->assertSame(UserRole::User->value, $log->changes['role'] ?? null);
        $this->assertArrayHasKey('team_id', (array) $log->changes);
    }

    public function test_member_role_change_audits_assigned_and_revoked(): void {
        // Factory-State user() weist die 'user'-Rolle bereits zu.
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->put(route('org.members.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => UserRole::Buchhaltung->value,
            ])
            ->assertRedirect(route('org.members.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role.assigned',
            'auditable_type' => User::class,
            'auditable_id' => $member->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role.revoked',
            'auditable_type' => User::class,
            'auditable_id' => $member->id,
        ]);
    }

    public function test_member_role_noop_update_writes_no_role_events(): void {
        // Rollenloser User; die Erst-Zuweisung läuft über denselben
        // Controller-Schreibweg, damit Vorher- und Nachher-Stand exakt der
        // Sync-Semantik des Controllers entsprechen.
        $member = User::factory()->create(['organization_id' => $this->organization->id]);

        $payload = [
            'name' => $member->name,
            'email' => $member->email,
            'role' => UserRole::User->value,
        ];

        $this->actingAs($this->admin)
            ->put(route('org.members.update', $member), $payload)
            ->assertRedirect(route('org.members.index'));

        $countAfterFirst = AuditLog::query()
            ->whereIn('event', ['user.role.assigned', 'user.role.revoked'])
            ->where('auditable_type', User::class)
            ->where('auditable_id', $member->id)
            ->count();
        $this->assertSame(1, $countAfterFirst, 'Erst-Zuweisung muss genau ein assigned-Event schreiben.');

        // Identischer zweiter Sync: No-Op → KEINE weiteren Events.
        $this->actingAs($this->admin)
            ->put(route('org.members.update', $member), $payload)
            ->assertRedirect(route('org.members.index'));

        $this->assertSame($countAfterFirst, AuditLog::query()
            ->whereIn('event', ['user.role.assigned', 'user.role.revoked'])
            ->where('auditable_type', User::class)
            ->where('auditable_id', $member->id)
            ->count());
    }

    public function test_access_member_update_audits_role_diff(): void {
        // Factory-State user() weist die 'user'-Rolle bereits zu.
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $orgRole = Role::create([
            'name' => 'projektleitung',
            'guard_name' => 'web',
            'team_id' => $this->organization->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.access.members.update', $member), [
                'roles' => [$orgRole->id],
                'groups' => [],
            ])
            ->assertRedirect(route('admin.access.members.index'));

        $assigned = AuditLog::query()
            ->where('event', 'user.role.assigned')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $member->id)
            ->firstOrFail();
        $this->assertSame('projektleitung', $assigned->changes['role'] ?? null);
        $this->assertSame($this->organization->id, (int) ($assigned->changes['team_id'] ?? 0));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role.revoked',
            'auditable_type' => User::class,
            'auditable_id' => $member->id,
        ]);
    }

    public function test_access_member_group_sync_audits_membership_diff(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $group = UserGroup::create([
            'organization_id' => $this->organization->id,
            'name' => 'Servicegruppe',
            'is_system' => false,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.access.members.update', $member), [
                'roles' => [],
                'groups' => [$group->id],
            ])
            ->assertRedirect(route('admin.access.members.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_group.member_added',
            'auditable_type' => UserGroup::class,
            'auditable_id' => $group->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.access.members.update', $member), [
                'roles' => [],
                'groups' => [],
            ])
            ->assertRedirect(route('admin.access.members.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_group.member_removed',
            'auditable_type' => UserGroup::class,
            'auditable_id' => $group->id,
        ]);
    }

    public function test_group_role_and_permission_sync_audits_diff_without_noop_events(): void {
        $group = UserGroup::create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechtegruppe',
            'is_system' => false,
        ]);
        $orgRole = Role::create([
            'name' => 'lagerleitung',
            'guard_name' => 'web',
            'team_id' => $this->organization->id,
        ]);

        // Vergabe: Rolle + Direkt-Permission.
        $this->actingAs($this->admin)
            ->put(route('admin.access.groups.update', $group), [
                'name' => $group->name,
                'roles' => [$orgRole->id],
                'permissions' => ['customer.viewAny'],
            ])
            ->assertRedirect(route('admin.access.groups.show', $group));

        $roleEvent = AuditLog::query()
            ->where('event', 'user.role.assigned')
            ->where('auditable_type', UserGroup::class)
            ->where('auditable_id', $group->id)
            ->firstOrFail();
        $this->assertSame('lagerleitung', $roleEvent->changes['role'] ?? null);

        $permEvent = AuditLog::query()
            ->where('event', 'user.permission.granted')
            ->where('auditable_type', UserGroup::class)
            ->where('auditable_id', $group->id)
            ->firstOrFail();
        $this->assertSame('customer.viewAny', $permEvent->changes['permission'] ?? null);

        // No-Op: identischer Sync erzeugt KEINE weiteren Events.
        $before = AuditLog::query()->where('event', 'like', 'user.%')->count();
        $this->actingAs($this->admin)
            ->put(route('admin.access.groups.update', $group), [
                'name' => $group->name,
                'roles' => [$orgRole->id],
                'permissions' => ['customer.viewAny'],
            ])
            ->assertRedirect(route('admin.access.groups.show', $group));
        $this->assertSame($before, AuditLog::query()->where('event', 'like', 'user.%')->count());

        // Entzug: Rolle + Permission wieder weg.
        $this->actingAs($this->admin)
            ->put(route('admin.access.groups.update', $group), [
                'name' => $group->name,
                'roles' => [],
                'permissions' => [],
            ])
            ->assertRedirect(route('admin.access.groups.show', $group));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role.revoked',
            'auditable_type' => UserGroup::class,
            'auditable_id' => $group->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.permission.revoked',
            'auditable_type' => UserGroup::class,
            'auditable_id' => $group->id,
        ]);
    }
}
