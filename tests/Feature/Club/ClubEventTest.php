<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubEventVisibility, ClubParticipationSource, ClubParticipationStatus};
use App\Enums\Event\{ParticipantRole, ParticipantStatus};
use App\Enums\User\UserRole;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventParticipation, ClubGroup, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\{ClubEventService, ClubGroupService, ClubMemberService};
use App\Services\Event\EventService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vereinstermine (Feature 159, MVP-843): Kapazität und Warteliste über alle
 * Teilnehmerarten, Soll-Liste ohne Doppelzählung, Gruppenzugriff, Serien mit
 * eigenen Listen, Fristen, Einladungen und der Erhalt von Anmeldungen bei
 * Verschiebung und Absage.
 */
class ClubEventTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function clubEvents(): ClubEventService {
        return app(ClubEventService::class);
    }

    private function groupService(): ClubGroupService {
        return app(ClubGroupService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function makeEvent(array $groupIds, array $overrides = []): Event {
        $start = CarbonImmutable::now()->addDays(3)->setTime(18, 0);

        return $this->clubEvents()->create($this->organization, $this->admin, array_merge([
            'title' => 'Training',
            'kind' => 'training',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => $groupIds,
            'started_at' => $start->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $start->addHours(2)->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
        ], $overrides));
    }

    private function memberIn(ClubGroup ...$groups): ClubMember {
        $member = ClubMember::factory()->aged(14)->create();
        foreach ($groups as $group) {
            $this->groupService()->admit($group, $member, CarbonImmutable::today()->subMonth(), $this->admin);
        }

        return $member;
    }

    // ── Kapazität / Warteliste ───────────────────────────────────────────

    public function test_last_seat_goes_to_the_first_registration_and_waitlist_moves_up_on_cancellation(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], ['max_participants' => 1]);
        $first = $this->memberIn($group);
        $second = $this->memberIn($group);

        $a = $this->clubEvents()->register($event, $first, $this->admin, ClubParticipationSource::Admin);
        $b = $this->clubEvents()->register($event, $second, $this->admin, ClubParticipationSource::Admin);
        $this->assertSame(ClubParticipationStatus::Registered, $a->status);
        $this->assertSame(ClubParticipationStatus::Waitlisted, $b->status);
        $this->assertSame($a->id, $this->clubEvents()->register($event, $first, $this->admin, ClubParticipationSource::Admin)->id, 'Wiederholte Anmeldung ist idempotent.');

        $this->clubEvents()->cancelRegistration($event, $first, $this->admin, true);

        $this->assertSame(ClubParticipationStatus::Cancelled, $a->refresh()->status);
        $this->assertSame(ClubParticipationStatus::Registered, $b->refresh()->status);
        $this->assertNotNull($b->promoted_at);
        $this->assertSame(1, ClubEventParticipation::query()->where('event_id', $event->id)->where('status', ClubParticipationStatus::Registered->value)->count());
    }

    public function test_capacity_is_shared_with_user_participants_of_the_same_event(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], ['max_participants' => 2]);
        $staff = $this->orgUser();
        app(EventService::class)->addParticipant($event, $staff, ParticipantRole::Attendee, ParticipantStatus::Accepted);

        $one = $this->clubEvents()->register($event, $this->memberIn($group), $this->admin, ClubParticipationSource::Admin);
        $two = $this->clubEvents()->register($event, $this->memberIn($group), $this->admin, ClubParticipationSource::Admin);

        $this->assertSame(ClubParticipationStatus::Registered, $one->status);
        $this->assertSame(ClubParticipationStatus::Waitlisted, $two->status, 'Der Benutzerplatz zählt mit.');
        $this->assertSame(['taken' => 2, 'free' => 0, 'max' => 2], $this->clubEvents()->seatSummary($event));
    }

    // ── Zielgruppen ──────────────────────────────────────────────────────

    public function test_member_in_two_target_groups_is_listed_once_and_outsiders_are_not_eligible(): void {
        $kids = ClubGroup::factory()->create();
        $advanced = ClubGroup::factory()->create();
        $both = $this->memberIn($kids, $advanced);
        $onlyKids = $this->memberIn($kids);
        $outsider = ClubMember::factory()->aged(30)->create();
        $event = $this->makeEvent([$kids->id, $advanced->id]);

        $targets = $this->clubEvents()->targetMembers($event);
        $this->assertSame(2, $targets->count());
        $this->assertSame(1, $targets->where('id', $both->id)->count(), 'Mitglied in zwei Zielgruppen zählt einmal.');
        $this->assertTrue($this->clubEvents()->isEligible($event, $onlyKids));
        $this->assertFalse($this->clubEvents()->isEligible($event, $outsider));

        try {
            $this->clubEvents()->register($event, $outsider, $this->admin, ClubParticipationSource::Self);
            $this->fail('Außenstehende dürfen sich nicht selbst anmelden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_member_id', $e->errors());
        }

        $spontaneous = $this->clubEvents()->register($event, $outsider, $this->admin, ClubParticipationSource::Spontaneous, null, true);
        $this->assertSame(ClubParticipationStatus::Registered, $spontaneous->status);
    }

    public function test_invited_visibility_requires_an_invitation(): void {
        $event = $this->makeEvent([], ['visibility' => ClubEventVisibility::Invited->value]);
        $member = ClubMember::factory()->aged(20)->create();

        $this->assertFalse($this->clubEvents()->isEligible($event, $member));
        $this->clubEvents()->invite($event, $member, $this->admin);
        $this->assertTrue($this->clubEvents()->isEligible($event, $member));
        $this->assertSame(1, $this->clubEvents()->targetMembers($event)->count());

        $participation = $this->clubEvents()->register($event, $member, $this->admin, ClubParticipationSource::Self);
        $this->assertSame(ClubParticipationStatus::Registered, $participation->status);
    }

    // ── Fristen ──────────────────────────────────────────────────────────

    public function test_registration_deadline_binds_members_but_not_a_spontaneous_addition(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], ['registration_lead_hours' => 96]);
        $member = $this->memberIn($group);

        try {
            $this->clubEvents()->register($event, $member, $this->admin, ClubParticipationSource::Self);
            $this->fail('Anmeldeschluss hätte gegriffen.');
        } catch (ValidationException $e) {
            $this->assertSame(__('club.events.error.registration_closed'), $e->errors()['club_member_id'][0]);
        }

        $forced = $this->clubEvents()->register($event, $member, $this->admin, ClubParticipationSource::Spontaneous, null, true);
        $this->assertSame(ClubParticipationStatus::Registered, $forced->status);
    }

    // ── Verschiebung / Absage / Serien ───────────────────────────────────

    public function test_reschedule_and_cancellation_keep_participations(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id]);
        $member = $this->memberIn($group);
        $participation = $this->clubEvents()->register($event, $member, $this->admin, ClubParticipationSource::Admin);

        $newStart = CarbonImmutable::now()->addDays(5)->setTime(19, 0);
        $this->clubEvents()->update($event, $this->admin, [
            'title' => 'Training (verschoben)',
            'kind' => 'training',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => [$group->id],
            'started_at' => $newStart->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $newStart->addHours(2)->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
        ]);
        $this->assertSame('Training (verschoben)', $event->refresh()->title);
        $this->assertSame(ClubParticipationStatus::Registered, $participation->refresh()->status);

        $this->clubEvents()->cancel($event, $this->admin, 'Halle gesperrt');
        $this->assertNotNull($event->refresh()->cancelled_at);
        $this->assertSame(ClubParticipationStatus::Registered, $participation->refresh()->status, 'Absage löscht keine Anmeldung.');

        $this->expectException(ValidationException::class);
        $this->clubEvents()->register($event, $this->memberIn($group), $this->admin, ClubParticipationSource::Admin, null, true);
    }

    public function test_series_occurrences_inherit_club_details_and_keep_separate_lists(): void {
        $group = ClubGroup::factory()->create();
        $master = $this->makeEvent([$group->id], [
            'recurrence' => 'weekly',
            'series_until' => CarbonImmutable::now()->addWeeks(3)->toDateString(),
        ]);
        $occurrences = $master->occurrences()->orderBy('started_at')->get();

        $this->assertGreaterThanOrEqual(2, $occurrences->count());
        foreach ($occurrences as $occurrence) {
            $this->assertNotNull($occurrence->clubDetails()->first(), 'Vorkommen erben die Vereinsdetails.');
            $this->assertSame([$group->id], $occurrence->clubGroups()->pluck('club_groups.id')->all());
        }

        $member = $this->memberIn($group);
        $first = $occurrences->first();
        $this->clubEvents()->register($first, $member, $this->admin, ClubParticipationSource::Admin);
        $this->assertSame(1, $first->clubParticipations()->count());
        $this->assertSame(0, $occurrences->last()->clubParticipations()->count(), 'Jeder Serientermin hat seine eigene Liste.');
        $this->assertSame(0, $master->clubParticipations()->count());
    }

    // ── Rechte ───────────────────────────────────────────────────────────

    public function test_group_lead_sees_and_manages_only_events_of_own_target_groups(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $own = ClubGroup::factory()->create(['leader_user_id' => $lead->id]);
        $other = ClubGroup::factory()->create();
        $ownEvent = $this->makeEvent([$own->id]);
        $otherEvent = $this->makeEvent([$other->id]);
        $member = $this->memberIn($own);

        $this->actingAs($lead)->get(route('club.events.show', $ownEvent))->assertOk();
        $this->actingAs($lead)->get(route('club.events.show', $otherEvent))->assertForbidden();
        $this->actingAs($lead)->get(route('club.events.index'))->assertOk()->assertSee($ownEvent->title)->assertDontSee($otherEvent->sqid);

        $this->actingAs($lead)->post(route('club.events.register', $ownEvent), ['club_member_id' => $member->sqid, 'mode' => 'register'])
            ->assertRedirect(route('club.events.show', $ownEvent));
        $this->assertSame(ClubParticipationSource::Leader, ClubEventParticipation::query()->firstOrFail()->source);
        $this->actingAs($lead)->post(route('club.events.register', $otherEvent), ['club_member_id' => $member->sqid, 'mode' => 'register'])
            ->assertForbidden();
        $this->actingAs($lead)->post(route('club.events.store'), ['title' => 'x', 'kind' => 'training', 'visibility' => 'groups', 'started_at' => '2030-01-01T10:00', 'ended_at' => '2030-01-01T11:00'])
            ->assertForbidden();
    }

    public function test_member_and_guardian_may_register_themselves_via_policy(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id]);
        $details = $event->clubDetails()->firstOrFail();
        $self = $this->orgUser();
        $parent = $this->orgUser();
        $adult = $this->memberIn($group);
        app(ClubMemberService::class)->update($adult, ['user_id' => $self->id]);
        $child = $this->memberIn($group);
        app(ClubMemberService::class)->addGuardian($child, ['name' => 'Elternteil', 'user_id' => $parent->id, 'permissions' => ['register']], $this->admin);

        $this->assertTrue(Gate::forUser($self)->allows('registerMember', [$details, $adult]));
        $this->assertFalse(Gate::forUser($self)->allows('registerMember', [$details, $child]));
        $this->assertTrue(Gate::forUser($parent)->allows('registerMember', [$details, $child]));
        $this->assertFalse(Gate::forUser($parent)->allows('registerMember', [$details, $adult]));
        $this->assertFalse(Gate::forUser($parent)->allows('view', $details));
    }

    public function test_pages_and_dialogs_render_for_the_administration(): void {
        $group = ClubGroup::factory()->create();
        $event = $this->makeEvent([$group->id], ['max_participants' => 5]);
        $this->clubEvents()->register($event, $this->memberIn($group), $this->admin, ClubParticipationSource::Admin);

        $this->actingAs($this->admin)->get(route('club.events.index'))->assertOk()->assertSee($event->title);
        $this->actingAs($this->admin)->get(route('club.events.index', ['period' => 'range']))->assertOk();
        $this->actingAs($this->admin)->get(route('club.events.show', $event))->assertOk()->assertSee('1 / 5');
        $this->actingAs($this->admin)->get(route('club.events.create'))->assertOk()->assertSee('name="recurrence"', false);
        $this->actingAs($this->admin)->get(route('club.events.edit', $event))->assertOk()->assertDontSee('name="recurrence"', false);
        $this->actingAs($this->admin)->get(route('club.events.register.create', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('club.events.cancel.edit', $event))->assertOk();
        $this->actingAs($this->admin)->get(route('events.show', $event))->assertOk();
    }
}
