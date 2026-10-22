<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MemberPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Org;

use App\Enums\User\{Permission, UserRole};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Mitgliederverwaltung über die Benutzerrechte (lk-06): Liste mit
 * user.viewAny, Anlegen, Ändern, Passwort und Entfernen je mit eigenem
 * Recht. Delegierte Rechte handeln nie an Admins und vergeben keine
 * Admin-Rolle.
 */
class MemberPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_member_list_follows_user_view_any(): void {
        $member = $this->orgUser();

        $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))
            ->get(route('org.members.index'))
            ->assertOk()
            ->assertSee($member->email)
            ->assertDontSee(route('org.members.create'), false)
            ->assertDontSee(route('org.members.edit', $member), false)
            ->assertDontSee(route('org.members.destroy', $member), false);

        $this->actingAs($this->orgUser())->get(route('org.members.index'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('org.members.index'))->assertOk();
    }

    public function test_delegated_create_cannot_hand_out_the_admin_role(): void {
        $creator = $this->actorIn($this->organization, [Permission::UserCreate]);

        $this->actingAs($creator)->get(route('org.members.create'))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))->get(route('org.members.create'))->assertForbidden();

        $this->actingAs($creator)
            ->post(route('org.members.store'), $this->newMember('admin-versuch@test.de', UserRole::Admin->value))
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'admin-versuch@test.de']);

        $this->actingAs($creator)
            ->post(route('org.members.store'), $this->newMember('neu@test.de', UserRole::User->value))
            ->assertRedirect(route('org.members.index'));
        $this->assertDatabaseHas('users', ['email' => 'neu@test.de', 'organization_id' => $this->organization->id]);
    }

    public function test_delegated_update_and_password_reset_never_touch_admins(): void {
        $editor = $this->actorIn($this->organization, [Permission::UserUpdate]);
        $member = $this->orgUser();
        $admin = $this->orgAdmin();

        $this->actingAs($editor)->get(route('org.members.edit', $member))->assertOk();
        $this->actingAs($editor)->get(route('org.members.edit', $admin))->assertForbidden();

        $this->actingAs($editor)
            ->put(route('org.members.update', $member), [
                'name' => 'Umbenannt',
                'email' => $member->email,
                'role' => UserRole::User->value,
                'new_password' => 'Password123!Strong',
                'new_password_confirmation' => 'Password123!Strong',
            ])
            ->assertSessionHasErrors('new_password');

        $resetter = $this->actorIn($this->organization, [Permission::UserUpdate, Permission::UserResetPassword]);
        $this->actingAs($resetter)
            ->put(route('org.members.update', $member), [
                'name' => 'Umbenannt',
                'email' => $member->email,
                'role' => UserRole::User->value,
                'new_password' => 'Password123!Strong',
                'new_password_confirmation' => 'Password123!Strong',
            ])
            ->assertRedirect(route('org.members.index'));
        $this->assertSame('Umbenannt', $member->refresh()->name);
        $this->assertTrue($member->must_change_password);
    }

    public function test_removing_members_follows_user_delete(): void {
        $member = $this->orgUser();
        $admin = $this->orgAdmin();
        $remover = $this->actorIn($this->organization, [Permission::UserDelete]);

        $this->actingAs($this->userWithRole(UserRole::Teamleitung->value))
            ->delete(route('org.members.destroy', $member))
            ->assertForbidden();
        $this->actingAs($remover)->delete(route('org.members.destroy', $admin))->assertForbidden();

        $this->actingAs($remover)
            ->delete(route('org.members.destroy', $member))
            ->assertRedirect(route('org.members.index'));
        $this->assertNull(User::query()->find($member->id));
    }

    /** @return array<string, string> */
    private function newMember(string $email, string $role): array {
        return [
            'name' => 'Neue Person',
            'email' => $email,
            'role' => $role,
            'password' => 'Password123!Strong',
            'password_confirmation' => 'Password123!Strong',
        ];
    }
}
