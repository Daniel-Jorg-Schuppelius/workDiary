<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Access;

use App\Enums\User\{Permission, UserRole};
use App\Models\Platform\{User, UserGroup};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Rechteverwaltung (lk-06): Rollen und Gruppen eines Mitglieds vergibt nur,
 * wer access.roles.assign bzw. access.groups.assign hat; das Audit-Log liest
 * audit-log.view.
 */
class AccessPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_assigning_roles_and_groups_needs_the_assign_rights(): void {
        $member = $this->orgUser();
        $group = UserGroup::create(['organization_id' => $this->organization->id, 'name' => 'Leitstelle', 'is_system' => false]);
        $leadRole = Role::query()->where('name', UserRole::Teamleitung->value)->where('team_id', $this->organization->id)->firstOrFail();
        $payload = ['roles' => [$leadRole->id], 'groups' => [$group->sqid]];

        $manager = $this->actorIn($this->organization, ['access.manage']);
        $this->actingAs($manager)->put(route('admin.access.members.update', $member), $payload)->assertRedirect();
        $this->assertFalse($this->freshMember($member)->hasRole(UserRole::Teamleitung->value));
        $this->assertFalse($group->members()->whereKey($member->id)->exists());
        $this->actingAs($manager)->post(route('admin.access.groups.members.attach', $group), ['user_id' => $member->id])->assertForbidden();

        $assigner = $this->actorIn($this->organization, ['access.manage', Permission::AccessAssignRoles, Permission::AccessAssignGroups]);
        $this->actingAs($assigner)->put(route('admin.access.members.update', $member), $payload)->assertRedirect();
        $this->assertTrue($this->freshMember($member)->hasRole(UserRole::Teamleitung->value));
        $this->assertTrue($group->members()->whereKey($member->id)->exists());
    }

    public function test_audit_log_follows_audit_log_view(): void {
        $this->actingAs($this->userWithRole(UserRole::Buchhaltung->value))->get(route('audit.index'))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))->get(route('audit.index'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('audit.index'))->assertOk();
    }

    private function freshMember(User $member): User {
        $this->actAsTeam($this->organization);

        return User::query()->findOrFail($member->id);
    }
}
