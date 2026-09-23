<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubGroupMembershipStatus, ClubMembershipKind};
use App\Enums\User\UserRole;
use App\Models\Club\{ClubGroup, ClubGuardian, ClubMember};
use App\Models\Platform\{Organization, User};
use App\Services\Club\{ClubGroupService, ClubMemberService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vereinsmitglieder (Feature 159, MVP-842): Anlage ohne Login, laufende
 * Nummern je Organisation, Verlauf und Austritt, Kontoverknüpfung,
 * Mandantengrenze, Rechte (Register, Gruppenleitung, Vertretung) und Plan-Gate.
 */
class ClubMemberTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function members(): ClubMemberService {
        return app(ClubMemberService::class);
    }

    private function groupService(): ClubGroupService {
        return app(ClubGroupService::class);
    }

    // ── Anlage / Nummern ─────────────────────────────────────────────────

    public function test_member_is_created_without_email_or_login_and_gets_sequential_no(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('club.members.store'), [
            'first_name' => 'Mia',
            'last_name' => 'Muster',
            'kind' => ClubMembershipKind::Active->value,
            'joined_on' => '2026-09-01',
        ])->assertRedirect();

        $member = ClubMember::query()->where('last_name', 'Muster')->firstOrFail();
        $this->assertSame(1, $member->member_no);
        $this->assertNull($member->email);
        $this->assertNull($member->user_id);
        $this->assertCount(1, $member->periods);
        $this->assertSame(ClubMembershipKind::Active, $member->periods->first()->kind);

        $second = $this->members()->create($this->organization, $admin, ['first_name' => 'Ben', 'last_name' => 'Muster']);
        $this->assertSame(2, $second->member_no);
    }

    public function test_two_children_with_the_same_contact_address_stay_separate_members(): void {
        $admin = $this->orgAdmin();
        $shared = ['email' => 'familie@example.test', 'street' => 'Hauptstraße 1', 'postal_code' => '12345', 'city' => 'Musterstadt'];

        $this->members()->create($this->organization, $admin, ['first_name' => 'Mia', 'last_name' => 'Muster', 'birth_date' => '2018-03-04'] + $shared);
        $this->members()->create($this->organization, $admin, ['first_name' => 'Ben', 'last_name' => 'Muster', 'birth_date' => '2020-07-09'] + $shared);

        $this->assertSame(2, ClubMember::query()->where('email', 'familie@example.test')->count());
    }

    public function test_member_no_is_unique_per_organization_only(): void {
        $admin = $this->orgAdmin();
        $this->members()->create($this->organization, $admin, ['member_no' => 7, 'first_name' => 'Mia', 'last_name' => 'Muster']);

        $this->actingAs($admin)->from(route('club.members.index'))->post(route('club.members.store'), [
            'member_no' => 7,
            'first_name' => 'Ben',
            'last_name' => 'Muster',
            'kind' => 'active',
            'joined_on' => '2026-09-01',
        ])->assertSessionHasErrors('member_no');

        $other = Organization::factory()->create();
        $foreign = $this->members()->create($other, null, ['member_no' => 7, 'first_name' => 'Lea', 'last_name' => 'Fremd']);
        $this->assertSame(7, $foreign->member_no);
        $this->assertSame($other->id, $foreign->organization_id);
    }

    // ── Verlauf / Austritt ───────────────────────────────────────────────

    public function test_kind_change_extends_history_and_leave_ends_group_memberships(): void {
        $admin = $this->orgAdmin();
        $member = $this->members()->create($this->organization, $admin, ['first_name' => 'Mia', 'last_name' => 'Muster', 'joined_on' => '2026-01-01', 'birth_date' => '2015-05-05']);
        $group = ClubGroup::factory()->create();
        $this->groupService()->admit($group, $member, CarbonImmutable::parse('2026-02-01'), $admin);

        $this->members()->changeKind($member, ClubMembershipKind::Paused, CarbonImmutable::parse('2026-06-01'), $admin, 'Verletzung');
        $member->refresh();
        $this->assertSame(ClubMembershipKind::Paused, $member->kind);
        $periods = $member->periods()->get();
        $this->assertCount(2, $periods);
        $this->assertSame('2026-05-31', $periods[0]->ends_on?->toDateString());
        $this->assertSame('2026-06-01', $periods[1]->starts_on->toDateString());
        $this->assertNull($periods[1]->ends_on);

        $this->members()->leave($member, CarbonImmutable::parse('2026-09-15'), $admin);
        $member->refresh();
        $this->assertSame('2026-09-15', $member->left_on?->toDateString());
        $this->assertSame('2026-09-15', $member->periods()->get()->last()?->ends_on?->toDateString());
        $membership = $member->groupMemberships()->firstOrFail();
        $this->assertSame(ClubGroupMembershipStatus::Ended, $membership->status);
        $this->assertSame('2026-09-15', $membership->valid_to?->toDateString());
        $this->assertTrue($member->hasLeftOn(CarbonImmutable::parse('2026-09-16')));
        $this->assertFalse($member->hasLeftOn(CarbonImmutable::parse('2026-09-15')));

        $this->expectException(ValidationException::class);
        $this->members()->changeKind($member, ClubMembershipKind::Active, CarbonImmutable::parse('2026-10-01'), $admin);
    }

    public function test_linked_account_must_belong_to_the_same_organization(): void {
        $admin = $this->orgAdmin();
        $member = $this->members()->create($this->organization, $admin, ['first_name' => 'Mia', 'last_name' => 'Muster']);
        $own = $this->orgUser();
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->members()->update($member, ['user_id' => $own->id]);
        $this->assertSame($own->id, $member->refresh()->user_id);

        $this->expectException(ValidationException::class);
        $this->members()->update($member, ['user_id' => $foreign->id]);
    }

    // ── Liste / Mandant / Rechte ─────────────────────────────────────────

    public function test_index_shows_current_members_of_own_organization_only(): void {
        $admin = $this->orgAdmin();
        $current = ClubMember::factory()->create(['last_name' => 'Aktuell']);
        $left = ClubMember::factory()->left()->create(['last_name' => 'Ehemalig']);
        $other = Organization::factory()->create();
        ClubMember::factory()->create(['organization_id' => $other->id, 'last_name' => 'Fremdverein']);

        $response = $this->actingAs($admin)->get(route('club.members.index'));
        $response->assertOk()->assertViewIs('club.members.index')
            ->assertSee($current->fullName())
            ->assertDontSee($left->fullName())
            ->assertDontSee('Fremdverein');

        $this->actingAs($admin)->get(route('club.members.index', ['status' => 'all']))
            ->assertOk()->assertSee($left->fullName());
    }

    public function test_user_without_club_rights_is_forbidden(): void {
        $user = $this->orgUser();
        $member = ClubMember::factory()->create();

        $this->actingAs($user)->get(route('club.members.index'))->assertForbidden();
        $this->actingAs($user)->get(route('club.members.show', $member))->assertForbidden();
        $this->actingAs($user)->post(route('club.members.store'), ['first_name' => 'X', 'last_name' => 'Y', 'kind' => 'active', 'joined_on' => '2026-09-01'])->assertForbidden();
    }

    public function test_module_gate_blocks_the_free_plan(): void {
        $org = Organization::factory()->free()->create();
        app()->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->get(route('club.members.index'))->assertStatus(423);
    }

    public function test_group_lead_sees_only_members_of_own_groups(): void {
        $admin = $this->orgAdmin();
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $ownGroup = ClubGroup::factory()->create(['leader_user_id' => $lead->id]);
        $otherGroup = ClubGroup::factory()->create();
        $inOwn = ClubMember::factory()->create(['last_name' => 'Eigene']);
        $inOther = ClubMember::factory()->create(['last_name' => 'Andere']);
        $this->groupService()->admit($ownGroup, $inOwn, CarbonImmutable::today(), $admin);
        $this->groupService()->admit($otherGroup, $inOther, CarbonImmutable::today(), $admin);

        $this->actingAs($lead)->get(route('club.members.index'))
            ->assertOk()->assertSee($inOwn->fullName())->assertDontSee($inOther->fullName());
        $this->actingAs($lead)->get(route('club.members.show', $inOwn))->assertOk();
        $this->actingAs($lead)->get(route('club.members.show', $inOther))->assertForbidden();
    }

    public function test_guardian_sees_only_assigned_children_until_revoked(): void {
        $admin = $this->orgAdmin();
        $parent = $this->orgUser();
        $childA = ClubMember::factory()->create(['last_name' => 'Kind A']);
        $childB = ClubMember::factory()->create(['last_name' => 'Kind B']);
        $guardian = $this->members()->addGuardian($childA, ['name' => 'Elternteil', 'user_id' => $parent->id, 'permissions' => ['register']], $admin);

        $this->actingAs($parent)->get(route('club.members.show', $childA))->assertOk();
        $this->actingAs($parent)->get(route('club.members.show', $childB))->assertForbidden();
        $this->actingAs($parent)->get(route('club.members.index'))->assertForbidden();

        $this->members()->revokeGuardian($guardian, $admin);
        $this->assertInstanceOf(ClubGuardian::class, ClubGuardian::query()->find($guardian->id));
        $this->actingAs($parent)->get(route('club.members.show', $childA))->assertForbidden();
    }

    public function test_member_dialogs_render_for_the_administration(): void {
        $admin = $this->orgAdmin();
        $member = ClubMember::factory()->create();
        $guardian = $this->members()->addGuardian($member, ['name' => 'Elternteil', 'permissions' => ['register']], $admin);

        $this->actingAs($admin)->get(route('club.members.create'))->assertOk()->assertSee('name="member_no"', false);
        $this->actingAs($admin)->get(route('club.members.edit', $member))->assertOk()->assertDontSee('name="joined_on"', false);
        $this->actingAs($admin)->get(route('club.members.kind.edit', $member))->assertOk();
        $this->actingAs($admin)->get(route('club.members.leave.edit', $member))->assertOk();
        $this->actingAs($admin)->get(route('club.members.guardians.create', $member))->assertOk()->assertSee('name="valid_from"', false);
        $this->actingAs($admin)->get(route('club.members.guardians.edit', [$member, $guardian]))->assertOk()->assertSee('Elternteil');
        $this->actingAs($admin)->get(route('club.members.show', $member))->assertOk()->assertSee($member->displayNo());
        $this->actingAs($admin)->get(route('club.groups.index'))->assertOk();
        $this->actingAs($admin)->get(route('club.departments.index'))->assertOk();
        $this->actingAs($admin)->get(route('club.proposals.index'))->assertOk();
    }

    public function test_member_sees_own_record_via_linked_account(): void {
        $admin = $this->orgAdmin();
        $self = $this->orgUser();
        $member = $this->members()->create($this->organization, $admin, ['first_name' => 'Ich', 'last_name' => 'Selbst', 'user_id' => $self->id]);
        $other = ClubMember::factory()->create();

        $this->actingAs($self)->get(route('club.members.show', $member))->assertOk();
        $this->actingAs($self)->get(route('club.members.show', $other))->assertForbidden();
    }
}
