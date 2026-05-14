<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class OrgMemberTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function regularUser(): User
    {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    // ── Zugriffskontrolle ────────────────────────────────────────────────────

    public function test_guest_cannot_access_members(): void
    {
        $this->get(route('org.members.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_members(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('org.members.index'))
            ->assertForbidden();
    }

    public function test_admin_without_org_cannot_view_members(): void
    {
        $adminNoOrg = User::factory()->admin()->create(['organization_id' => null]);

        $this->actingAs($adminNoOrg)
            ->get(route('org.members.index'))
            ->assertForbidden();
    }

    public function test_org_admin_can_view_member_list(): void
    {
        $admin = $this->admin();
        $this->regularUser(); // Mitglied anlegen

        $this->actingAs($admin)
            ->get(route('org.members.index'))
            ->assertOk()
            ->assertViewIs('org.members.index');
    }

    // ── Mitglied anlegen ─────────────────────────────────────────────────────

    public function test_admin_can_create_member(): void
    {
        $this->actingAs($this->admin())
            ->post(route('org.members.store'), [
                'name' => 'Neue Person',
                'email' => 'neu@test.de',
                'role' => User::ROLE_USER,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertRedirect(route('org.members.index'));

        $new = User::where('email', 'neu@test.de')->first();
        $this->assertNotNull($new);
        $this->assertSame($this->organization->id, $new->organization_id);
        $this->assertTrue($new->hasRole(User::ROLE_USER));
        $this->assertTrue($new->must_change_password);
    }

    public function test_store_validates_unique_email(): void
    {
        User::factory()->create(['email' => 'used@test.de']);

        $this->actingAs($this->admin())
            ->post(route('org.members.store'), [
                'name' => 'Jemand',
                'email' => 'used@test.de',
                'role' => User::ROLE_USER,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertSessionHasErrors('email');
    }

    // ── Mitglied bearbeiten ───────────────────────────────────────────────────

    public function test_admin_can_update_member(): void
    {
        $admin = $this->admin();
        $member = $this->regularUser();

        $this->actingAs($admin)
            ->put(route('org.members.update', $member), [
                'name' => 'Geändert',
                'email' => $member->email,
                'role' => User::ROLE_BUCHHALTUNG,
            ])
            ->assertRedirect(route('org.members.index'));

        $this->assertSame('Geändert', $member->fresh()->name);
        $this->assertTrue($member->fresh()->hasRole(User::ROLE_BUCHHALTUNG));
    }

    // ── Mitglied entfernen ────────────────────────────────────────────────────

    public function test_admin_can_delete_member(): void
    {
        $admin = $this->admin();
        $member = $this->regularUser();

        $this->actingAs($admin)
            ->delete(route('org.members.destroy', $member))
            ->assertRedirect(route('org.members.index'));

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('org.members.destroy', $admin))
            ->assertRedirect();

        // User soll noch existieren
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ── Cross-Org-Schutz ─────────────────────────────────────────────────────

    public function test_admin_cannot_edit_member_from_other_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherMember = User::factory()->user()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->admin())
            ->get(route('org.members.edit', $otherMember))
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_member_from_other_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherMember = User::factory()->user()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->admin())
            ->delete(route('org.members.destroy', $otherMember))
            ->assertForbidden();
    }
}
