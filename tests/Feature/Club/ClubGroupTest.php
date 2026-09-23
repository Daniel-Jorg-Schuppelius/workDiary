<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubCriteriaResult, ClubGroupMembershipStatus, ClubProposalStatus};
use App\Enums\User\UserRole;
use App\Models\Club\{ClubGroup, ClubGroupChangeProposal, ClubGroupMembership, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\ClubGroupService;
use App\Services\Notification\DeadlineScans\{ClubGroupCriteriaScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vereinsgruppen (Feature 159, MVP-842): Alterskriterien am Stichtag,
 * Kapazität, Mehrfachzuordnung, Anträge, Geburtstag → Wechselvorschlag statt
 * automatischer Entfernung, Bestätigung mit Wirksamkeitsdatum, Rechte der
 * Gruppenleitung.
 */
class ClubGroupTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function groupService(): ClubGroupService {
        return app(ClubGroupService::class);
    }

    private function today(): CarbonImmutable {
        return CarbonImmutable::today();
    }

    // ── Aufnahme ─────────────────────────────────────────────────────────

    public function test_admission_checks_age_criteria_capacity_and_duplicates(): void {
        $kids = ClubGroup::factory()->ageRange(6, 11)->capacity(2)->create();
        $eight = ClubMember::factory()->aged(8)->create();
        $twelve = ClubMember::factory()->aged(12)->create();
        $unknown = ClubMember::factory()->withoutBirthDate()->create();

        $membership = $this->groupService()->admit($kids, $eight, $this->today(), $this->admin);
        $this->assertSame(ClubGroupMembershipStatus::Active, $membership->status);

        try {
            $this->groupService()->admit($kids, $twelve, $this->today(), $this->admin);
            $this->fail('Zu altes Mitglied hätte abgelehnt werden müssen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_member_id', $e->errors());
        }
        $this->assertSame(ClubCriteriaResult::AgeAbove, $this->groupService()->evaluate($kids, $twelve, $this->today()));
        $this->assertSame(ClubCriteriaResult::ReviewRequired, $this->groupService()->evaluate($kids, $unknown, $this->today()));

        try {
            $this->groupService()->admit($kids, $unknown, $this->today(), $this->admin, true);
            $this->fail('Ausnahme ohne Begründung hätte abgelehnt werden müssen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }
        $this->groupService()->admit($kids, $unknown, $this->today(), $this->admin, true, 'Geburtsdatum folgt');
        $this->assertSame(2, $kids->activeMemberCountOn($this->today()));

        try {
            $this->groupService()->admit($kids, ClubMember::factory()->aged(7)->create(), $this->today(), $this->admin);
            $this->fail('Obergrenze hätte gegriffen.');
        } catch (ValidationException $e) {
            $this->assertSame(__('club.error.group_full'), $e->errors()['club_member_id'][0]);
        }

        $this->expectException(ValidationException::class);
        $this->groupService()->admit($kids, $eight, $this->today(), $this->admin);
    }

    public function test_member_belongs_to_several_groups_and_a_common_event_counts_once_per_member(): void {
        $training = ClubGroup::factory()->create();
        $advanced = ClubGroup::factory()->create();
        $member = ClubMember::factory()->aged(20)->create();

        $this->groupService()->admit($training, $member, $this->today(), $this->admin);
        $this->groupService()->admit($advanced, $member, $this->today(), $this->admin);

        $this->assertSame(2, $member->activeGroupMemberships()->count());
        $this->assertSame(1, ClubMember::query()->whereHas('activeGroupMemberships', fn($q) => $q->whereIn('club_group_id', [$training->id, $advanced->id]))->count());
    }

    public function test_application_is_checked_on_approval(): void {
        $group = ClubGroup::factory()->byApplication()->ageRange(6, 11)->create();
        $member = ClubMember::factory()->aged(9)->create();
        $tooOld = ClubMember::factory()->aged(14)->create();

        $request = $this->groupService()->request($group, $member, $this->today(), $this->admin);
        $this->assertSame(ClubGroupMembershipStatus::Requested, $request->status);
        $this->assertSame(0, $group->activeMemberCountOn($this->today()));

        $this->groupService()->approve($request, $this->admin);
        $this->assertSame(ClubGroupMembershipStatus::Active, $request->refresh()->status);

        $oldRequest = $this->groupService()->request($group, $tooOld, $this->today(), $this->admin);
        try {
            $this->groupService()->approve($oldRequest, $this->admin);
            $this->fail('Freigabe außerhalb der Altersgrenze hätte scheitern müssen.');
        } catch (ValidationException) {
        }
        $this->groupService()->reject($oldRequest, $this->admin, 'zu alt');
        $this->assertSame(ClubGroupMembershipStatus::Rejected, $oldRequest->refresh()->status);
    }

    // ── Vorschläge ───────────────────────────────────────────────────────

    public function test_birthday_creates_a_proposal_instead_of_removing_the_member(): void {
        $kids = ClubGroup::factory()->ageRange(6, 11)->create(['name' => 'Kinder']);
        $youth = ClubGroup::factory()->ageRange(12, 17)->create(['name' => 'Jugend', 'club_department_id' => $kids->club_department_id]);
        // Heute 12, vor einem Jahr 11: Aufnahme am damaligen Stichtag war korrekt.
        $member = ClubMember::factory()->aged(12)->create();
        $this->groupService()->admit($kids, $member, $this->today()->subYear(), $this->admin);

        $this->assertSame(1, $this->groupService()->refreshProposals($this->organization, $this->today()));
        $this->assertSame(0, $this->groupService()->refreshProposals($this->organization, $this->today()), 'Wiederholung darf keinen zweiten Vorschlag anlegen.');

        $proposal = ClubGroupChangeProposal::query()->firstOrFail();
        $this->assertSame(ClubCriteriaResult::AgeAbove, $proposal->reason);
        $this->assertSame(ClubProposalStatus::Open, $proposal->status);
        $this->assertSame($youth->id, $proposal->suggested_group_id, 'Genau eine passende Gruppe derselben Abteilung wird vorgeschlagen.');
        $this->assertSame(1, $kids->activeMemberCountOn($this->today()), 'Nichts wird automatisch entfernt.');

        $this->groupService()->confirmProposal($proposal, $this->admin, $this->today(), $youth);
        $this->assertSame(ClubProposalStatus::Confirmed, $proposal->refresh()->status);
        $old = ClubGroupMembership::query()->where('club_group_id', $kids->id)->firstOrFail();
        $this->assertSame(ClubGroupMembershipStatus::Ended, $old->status);
        $this->assertSame($this->today()->subDay()->toDateString(), $old->valid_to?->toDateString());
        $new = ClubGroupMembership::query()->where('club_group_id', $youth->id)->firstOrFail();
        $this->assertSame(ClubGroupMembershipStatus::Active, $new->status);
        $this->assertSame($this->today()->toDateString(), $new->valid_from->toDateString());
    }

    public function test_proposal_is_dismissed_when_criteria_are_met_again(): void {
        $kids = ClubGroup::factory()->ageRange(6, 11)->create();
        $member = ClubMember::factory()->aged(12)->create();
        $this->groupService()->admit($kids, $member, $this->today()->subYear(), $this->admin);
        $this->groupService()->refreshProposals($this->organization, $this->today());

        $this->groupService()->updateGroup($kids, ['name' => $kids->name, 'admission_mode' => 'leader', 'min_age' => 6, 'max_age' => 12]);
        $this->assertSame(0, $this->groupService()->refreshProposals($this->organization, $this->today()));

        $this->assertSame(ClubProposalStatus::Dismissed, ClubGroupChangeProposal::query()->firstOrFail()->status);
    }

    public function test_daily_scan_runs_per_organization(): void {
        $kids = ClubGroup::factory()->ageRange(6, 11)->create();
        $this->groupService()->admit($kids, ClubMember::factory()->aged(12)->create(), $this->today()->subYear(), $this->admin);

        $created = app(ClubGroupCriteriaScan::class)->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30));

        $this->assertSame(1, $created);
        $this->assertSame(1, ClubGroupChangeProposal::query()->where('status', ClubProposalStatus::Open->value)->count());
    }

    // ── Rechte ───────────────────────────────────────────────────────────

    public function test_group_lead_decides_only_in_own_groups_and_never_overrides(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $own = ClubGroup::factory()->ageRange(6, 11)->create(['leader_user_id' => $lead->id]);
        $other = ClubGroup::factory()->create();
        $child = ClubMember::factory()->aged(8)->create();
        $teen = ClubMember::factory()->aged(15)->create();

        $this->actingAs($lead)->get(route('club.groups.show', $own))->assertOk();
        $this->actingAs($lead)->get(route('club.groups.show', $other))->assertForbidden();

        $this->actingAs($lead)->post(route('club.groups.admit', $other), ['club_member_id' => $child->sqid, 'valid_from' => $this->today()->toDateString()])
            ->assertForbidden();
        $this->actingAs($lead)->post(route('club.groups.admit', $own), ['club_member_id' => $child->sqid, 'valid_from' => $this->today()->toDateString()])
            ->assertRedirect(route('club.groups.show', $own));
        $this->assertSame(1, $own->activeMemberCountOn($this->today()));

        $this->actingAs($lead)->post(route('club.groups.admit', $own), ['club_member_id' => $teen->sqid, 'valid_from' => $this->today()->toDateString(), 'override' => '1', 'note' => 'Ausnahme'])
            ->assertForbidden();
        $this->assertSame(1, $own->activeMemberCountOn($this->today()));
    }

    public function test_admin_admits_with_override_via_dialog_route(): void {
        $group = ClubGroup::factory()->ageRange(6, 11)->create();
        $teen = ClubMember::factory()->aged(15)->create();

        $this->actingAs($this->admin)->get(route('club.groups.admit.create', $group))->assertOk()->assertSee($teen->fullName());
        $this->actingAs($this->admin)->post(route('club.groups.admit', $group), ['club_member_id' => $teen->sqid, 'valid_from' => $this->today()->toDateString(), 'override' => '1', 'note' => 'Trainerentscheidung'])
            ->assertRedirect(route('club.groups.show', $group));
        $this->assertSame(1, $group->activeMemberCountOn($this->today()));
    }

    public function test_group_with_active_members_cannot_be_deleted(): void {
        $group = ClubGroup::factory()->create();
        $this->groupService()->admit($group, ClubMember::factory()->aged(30)->create(), $this->today(), $this->admin);

        $this->actingAs($this->admin)->from(route('club.groups.show', $group))->delete(route('club.groups.destroy', $group))
            ->assertSessionHasErrors('name');
        $this->assertNotNull(ClubGroup::query()->find($group->id));
    }
}
