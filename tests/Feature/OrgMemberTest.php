<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgMemberTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\UserRole;
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class OrgMemberTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function management(): User {
        return User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);
    }

    // ── Zugriffskontrolle ────────────────────────────────────────────────────

    public function test_guest_cannot_access_members(): void {
        $this->get(route('org.members.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_members(): void {
        $this->actingAs($this->orgUser())
            ->get(route('org.members.index'))
            ->assertForbidden();
    }

    public function test_admin_without_org_cannot_view_members(): void {
        $adminNoOrg = User::factory()->admin()->create(['organization_id' => null]);

        $this->actingAs($adminNoOrg)
            ->get(route('org.members.index'))
            ->assertForbidden();
    }

    public function test_org_admin_can_view_member_list(): void {
        $admin = $this->orgAdmin();
        $this->orgUser(); // Mitglied anlegen

        $this->actingAs($admin)
            ->get(route('org.members.index'))
            ->assertOk()
            ->assertViewIs('org.members.index');
    }

    public function test_management_can_view_member_list_for_hourly_wage_maintenance(): void {
        $management = $this->management();
        $this->orgUser();

        $this->actingAs($management)
            ->get(route('org.members.index'))
            ->assertOk()
            ->assertDontSee(__('Mitarbeiter anlegen'));
    }

    // ── Mitglied anlegen ─────────────────────────────────────────────────────

    public function test_admin_can_create_member(): void {
        $this->actingAs($this->orgAdmin())
            ->post(route('org.members.store'), [
                'name' => 'Neue Person',
                'personnel_number' => 'P-1001',
                'payroll_hourly_wage' => '22.50',
                'tax_identification_number' => '86095742719',
                'social_security_number' => '12 010170 A 123',
                'date_of_birth' => '1990-01-15',
                'health_insurance' => 'Techniker Krankenkasse',
                'tax_class' => '1',
                'child_allowances' => '0.50',
                'church_tax' => '1',
                'employment_start_date' => '2026-01-01',
                'email' => 'neu@test.de',
                'role' => UserRole::User->value,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertRedirect(route('org.members.index'));

        $new = User::where('email', 'neu@test.de')->first();
        $this->assertNotNull($new);
        $this->assertSame($this->organization->id, $new->organization_id);
        $this->assertSame('P-1001', $new->personnel_number);
        // Admin hält user.payroll.manage → darf den Stundenlohn setzen.
        $this->assertSame('22.50', $new->payroll_hourly_wage?->getAmount());
        $this->assertSame('86095742719', $new->tax_identification_number);
        $this->assertSame('12 010170 A 123', $new->social_security_number);
        $this->assertSame('1990-01-15', $new->date_of_birth?->format('Y-m-d'));
        $this->assertSame('Techniker Krankenkasse', $new->health_insurance);
        $this->assertSame('1', $new->tax_class);
        $this->assertSame('0.50', (string) $new->child_allowances);
        $this->assertTrue($new->church_tax);
        $this->assertSame('2026-01-01', $new->employment_start_date?->format('Y-m-d'));
        $this->assertTrue($new->hasRole(UserRole::User->value));
        $this->assertTrue($new->must_change_password);
    }

    public function test_store_validates_unique_email(): void {
        User::factory()->create(['email' => 'used@test.de']);

        $this->actingAs($this->orgAdmin())
            ->post(route('org.members.store'), [
                'name' => 'Jemand',
                'email' => 'used@test.de',
                'role' => UserRole::User->value,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_store_validates_unique_personnel_number_within_org(): void {
        User::factory()->create([
            'organization_id' => $this->organization->id,
            'personnel_number' => 'P-1001',
        ]);

        $this->actingAs($this->orgAdmin())
            ->post(route('org.members.store'), [
                'name' => 'Jemand',
                'personnel_number' => 'P-1001',
                'email' => 'personnel@test.de',
                'role' => UserRole::User->value,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertSessionHasErrors('personnel_number');
    }

    public function test_same_personnel_number_is_allowed_in_other_org(): void {
        $otherOrg = Organization::factory()->create();
        User::factory()->create([
            'organization_id' => $otherOrg->id,
            'personnel_number' => 'P-1001',
        ]);

        $this->actingAs($this->orgAdmin())
            ->post(route('org.members.store'), [
                'name' => 'Jemand',
                'personnel_number' => 'P-1001',
                'email' => 'personnel@test.de',
                'role' => UserRole::User->value,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertRedirect(route('org.members.index'));

        $this->assertDatabaseHas('users', [
            'organization_id' => $this->organization->id,
            'personnel_number' => 'P-1001',
            'email' => 'personnel@test.de',
        ]);
    }

    // ── Mitglied bearbeiten ───────────────────────────────────────────────────

    public function test_admin_can_update_member(): void {
        $admin = $this->orgAdmin();
        $member = $this->orgUser();

        $this->actingAs($admin)
            ->put(route('org.members.update', $member), [
                'name' => 'Geändert',
                'personnel_number' => 'P-2002',
                'payroll_hourly_wage' => '25.00',
                'tax_identification_number' => '98765432109',
                'date_of_birth' => '1985-05-20',
                'employment_start_date' => '2025-01-01',
                'employment_end_date' => '2026-12-31',
                'email' => $member->email,
                'role' => UserRole::Buchhaltung->value,
            ])
            ->assertRedirect(route('org.members.index'));

        $this->assertSame('Geändert', $member->fresh()->name);
        $this->assertSame('P-2002', $member->fresh()->personnel_number);
        $this->assertSame('25.00', $member->fresh()->payroll_hourly_wage?->getAmount());
        $this->assertSame('98765432109', $member->fresh()->tax_identification_number);
        $this->assertSame('1985-05-20', $member->fresh()->date_of_birth?->format('Y-m-d'));
        $this->assertSame('2025-01-01', $member->fresh()->employment_start_date?->format('Y-m-d'));
        $this->assertSame('2026-12-31', $member->fresh()->employment_end_date?->format('Y-m-d'));
        $this->assertTrue($member->fresh()->hasRole(UserRole::Buchhaltung->value));
    }

    public function test_management_can_update_payroll_block_but_not_identity(): void {
        $management = $this->management();
        $member = $this->orgUser();
        $originalName = $member->name;

        $this->actingAs($management)
            ->get(route('org.members.edit', $member))
            ->assertOk()
            ->assertSee(__('Stundenlohn'));

        $this->actingAs($management)
            ->put(route('org.members.update', $member), [
                'payroll_hourly_wage' => '24.75',
                'tax_identification_number' => '11111111111',
                // Identität/Rolle dürfen NICHT durchschlagen.
                'name' => 'Darf nicht geändert werden',
                'role' => UserRole::Admin->value,
            ])
            ->assertRedirect(route('org.members.index'));

        $member->refresh();
        // Voller Payroll-Block editierbar (inkl. Lohn).
        $this->assertSame('24.75', $member->payroll_hourly_wage?->getAmount());
        $this->assertSame('11111111111', $member->tax_identification_number);
        // Identität/Rolle unverändert.
        $this->assertSame($originalName, $member->name);
        $this->assertTrue($member->hasRole(UserRole::User->value));
        $this->assertFalse($member->hasRole(UserRole::Admin->value));
    }

    public function test_compensation_rate_is_required_for_time_based_compensation(): void {
        $management = $this->management();
        $member = $this->orgUser();

        $this->actingAs($management)
            ->put(route('org.members.update', $member), [
                'compensation_model' => 'nach_zeitaufwand',
                'compensation_rate' => '',
            ])
            ->assertSessionHasErrors('compensation_rate');

        $this->assertSame(
            'Das Feld Stundensatz ist erforderlich, wenn Vergütungsmodell gleich Nach Zeitaufwand ist.',
            session('errors')->first('compensation_rate'),
        );
        $this->assertNull($member->fresh()->compensation_model);
    }

    // ── Personalverwaltung (HR) ───────────────────────────────────────────────

    private function personnelAdmin(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_personnel_admin_can_view_and_maintain_payroll(): void {
        $hr = $this->personnelAdmin();
        $member = $this->orgUser();
        $originalName = $member->name;

        $this->actingAs($hr)->get(route('org.members.index'))->assertOk();

        $this->actingAs($hr)
            ->put(route('org.members.update', $member), [
                'payroll_hourly_wage' => '19.90',
                'tax_class' => '3',
                'name' => 'Hack',
                'role' => UserRole::Admin->value,
            ])
            ->assertRedirect(route('org.members.index'));

        $member->refresh();
        $this->assertSame('19.90', $member->payroll_hourly_wage?->getAmount());
        $this->assertSame('3', $member->tax_class);
        $this->assertSame($originalName, $member->name);
        $this->assertTrue($member->hasRole(UserRole::User->value));
    }

    public function test_personnel_admin_cannot_create_or_delete_members(): void {
        $hr = $this->personnelAdmin();
        $member = $this->orgUser();

        $this->actingAs($hr)
            ->post(route('org.members.store'), [
                'name' => 'Neu',
                'email' => 'hrneu@test.de',
                'role' => UserRole::User->value,
                'password' => 'Password123!Strong',
                'password_confirmation' => 'Password123!Strong',
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->delete(route('org.members.destroy', $member))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    // ── Mitglied entfernen ────────────────────────────────────────────────────

    public function test_admin_can_delete_member(): void {
        $admin = $this->orgAdmin();
        $member = $this->orgUser();

        $this->actingAs($admin)
            ->delete(route('org.members.destroy', $member))
            ->assertRedirect(route('org.members.index'));

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_admin_cannot_delete_themselves(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->delete(route('org.members.destroy', $admin))
            ->assertRedirect();

        // User soll noch existieren
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ── Cross-Org-Schutz ─────────────────────────────────────────────────────

    public function test_admin_cannot_edit_member_from_other_org(): void {
        $otherOrg = Organization::factory()->create();
        $otherMember = User::factory()->user()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->orgAdmin())
            ->get(route('org.members.edit', $otherMember))
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_member_from_other_org(): void {
        $otherOrg = Organization::factory()->create();
        $otherMember = User::factory()->user()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->orgAdmin())
            ->delete(route('org.members.destroy', $otherMember))
            ->assertForbidden();
    }
}
