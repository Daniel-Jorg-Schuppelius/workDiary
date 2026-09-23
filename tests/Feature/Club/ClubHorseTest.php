<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubEventVisibility, ClubParticipationSource};
use App\Enums\Notification\NotificationEvent;
use App\Enums\User\UserRole;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubGroup, ClubHorse, ClubHorseAssignment, ClubHorseUse, ClubMember, ClubResourceBooking};
use App\Models\Platform\User;
use App\Services\Club\{ClubAttendanceService, ClubEventService, ClubHorseService, ClubResourceService};
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Reitbetrieb (Feature 159, MVP-854): Ein Schulpferd nicht zeitgleich doppelt
 * vergeben; gesperrtes Pferd nicht zuteilbar; Ersatz benötigt Prüfung;
 * Pferdewechsel verdoppelt Reiteranwesenheit nicht.
 */
class ClubHorseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubGroup $group;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->group = ClubGroup::factory()->create(['name' => 'Reitgruppe Anfänger', 'leader_user_id' => $this->admin->id]);
    }

    private function horses(): ClubHorseService {
        return app(ClubHorseService::class);
    }

    /** @param  array<string, mixed>  $attributes */
    private function horse(string $name, array $attributes = []): ClubHorse {
        return $this->horses()->create($this->organization, $attributes + ['name' => $name, 'kind' => 'school', 'requires_clearance' => false, 'rest_minutes' => 0]);
    }

    private function lesson(string $start, int $minutes = 60): Event {
        $begin = CarbonImmutable::parse($start, 'Europe/Berlin');

        return app(ClubEventService::class)->create($this->organization, $this->admin, [
            'title' => 'Reitstunde ' . $start,
            'kind' => 'training',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => [$this->group->id],
            'started_at' => $begin->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $begin->addMinutes($minutes)->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
            'leader_user_id' => $this->admin->id,
        ]);
    }

    private function rider(): ClubMember {
        $member = ClubMember::factory()->aged(12)->create();
        app(\App\Services\Club\ClubGroupService::class)->admit($this->group, $member, CarbonImmutable::today()->subMonth(), $this->admin);

        return $member;
    }

    public function test_a_school_horse_is_never_assigned_twice_at_the_same_time_and_rest_buffer_counts(): void {
        $horse = $this->horse('Fanny', ['rest_minutes' => 30]);
        $first = $this->lesson('2026-10-13 15:00');
        $overlap = $this->lesson('2026-10-13 15:30');
        $tooClose = $this->lesson('2026-10-13 16:15');
        $fine = $this->lesson('2026-10-13 16:30');
        [$anna, $ben] = [$this->rider(), $this->rider()];

        $assignment = $this->horses()->assign($first, $anna, $horse, $this->admin);
        $this->assertNotNull($assignment->club_resource_booking_id, 'Zuordnung belegt die Pferde-Ressource.');
        try {
            $this->horses()->assign($overlap, $ben, $horse, $this->admin);
            $this->fail('Zeitgleich doppelt vergeben.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        try {
            $this->horses()->assign($tooClose, $ben, $horse, $this->admin);
            $this->fail('Ruhepuffer von 30 Minuten greift.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        $this->horses()->assign($fine, $ben, $horse, $this->admin);
        $this->assertSame(2, ClubHorseAssignment::query()->where('club_horse_id', $horse->id)->count());

        // Zwei Reiter, ein Pferd, dieselbe Stunde: nein — ein Pferd trägt eine Person.
        try {
            $this->horses()->assign($first, $ben, $horse, $this->admin);
            $this->fail('Ein Pferd je Stunde nur einmal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        // Wechsel: Anna bekommt ein anderes Pferd, Fanny wird frei.
        $second = $this->horse('Max');
        $this->horses()->assign($first, $anna, $second, $this->admin);
        $this->assertSame(1, ClubHorseAssignment::query()->where('event_id', $first->id)->count());
        $this->horses()->assign($first, $ben, $horse, $this->admin);
        $this->assertSame(2, ClubResourceBooking::query()->where('event_id', $first->id)->count());
    }

    public function test_a_blocked_horse_cannot_be_assigned_existing_lessons_are_flagged_and_the_leader_is_notified(): void {
        $horse = $this->horse('Luna');
        $lesson = $this->lesson('2026-10-14 15:00');
        $rider = $this->rider();
        $this->horses()->assign($lesson, $rider, $horse, $this->admin);
        $resource = $horse->resource()->firstOrFail();

        $this->mock(NotificationDispatcher::class, function ($mock): void {
            $mock->shouldReceive('notify')->once()->withArgs(fn(NotificationEvent $event, $subject, $user): bool => $event === NotificationEvent::ClubHorseUnavailable && $user->id === $this->admin->id)->andReturn(1);
        });
        app(ClubResourceService::class)->close($resource, CarbonImmutable::parse('2026-10-14 00:00', 'Europe/Berlin')->utc(), CarbonImmutable::parse('2026-10-16 00:00', 'Europe/Berlin')->utc(), 'Lahmt', $this->admin);

        $assignment = ClubHorseAssignment::query()->where('event_id', $lesson->id)->firstOrFail();
        $this->assertTrue($assignment->needsReview(), 'Stunde zur Neuplanung markiert, nicht gelöscht.');
        $this->assertStringContainsString('Lahmt', (string) $assignment->review_reason);
        $this->assertSame(1, ClubHorseAssignment::query()->where('event_id', $lesson->id)->count());

        $other = $this->lesson('2026-10-15 15:00');
        try {
            $this->horses()->assign($other, $this->rider(), $horse, $this->admin);
            $this->fail('Gesperrtes Pferd nicht zuteilbar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
        try {
            $this->horses()->assign($other, $this->rider(), $horse, $this->admin, false, true, 'trotzdem');
            $this->fail('Sperrzeit lässt sich nicht übergehen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_resource_id', $e->errors());
        }
    }

    public function test_a_replacement_needs_the_same_checks_clearance_and_daily_limit_only_with_explicit_override(): void {
        $checked = $this->horse('Pauline', ['requires_clearance' => true, 'max_uses_per_day' => 2]);
        $rider = $this->rider();
        $lesson = $this->lesson('2026-10-16 15:00');

        try {
            $this->horses()->assign($lesson, $rider, $checked, $this->admin);
            $this->fail('Ohne Eignungsfreigabe keine Zuordnung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_horse_id', $e->errors());
        }
        try {
            $this->horses()->assign($lesson, $rider, $checked, $this->admin, false, true);
            $this->fail('Übergehen braucht eine Begründung.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('override_note', $e->errors());
        }
        $override = $this->horses()->assign($lesson, $rider, $checked, $this->admin, false, true, 'Reitlehrerin führt am Zügel');
        $this->assertSame('Reitlehrerin führt am Zügel', $override->override_note);

        app(ClubResourceService::class)->grantClearance($checked->resource()->firstOrFail(), $rider, $this->admin);
        $second = $this->lesson('2026-10-16 16:00');
        $third = $this->lesson('2026-10-16 17:00');
        $this->horses()->assign($second, $this->cleared($checked), $checked, $this->admin);
        try {
            $this->horses()->assign($third, $this->cleared($checked), $checked, $this->admin);
            $this->fail('Einsatzgrenze 2 je Tag.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Pauline', implode(' ', $e->errors()['club_horse_id']));
        }
        $this->horses()->assign($third, $this->cleared($checked), $checked, $this->admin, false, true, 'Kurze Einheit, Tierarzt einverstanden');
        $this->assertSame(3, $this->horses()->usesOnDay($checked, CarbonImmutable::parse('2026-10-16', 'Europe/Berlin')));

        // Eigenes Pferd ohne Vereinsprofil ist ausdrücklich; ohne beides keine Zuordnung.
        $own = $this->horses()->assign($lesson, $this->rider(), null, $this->admin, true);
        $this->assertTrue($own->own_horse);
        try {
            $this->horses()->assign($lesson, $this->rider(), null, $this->admin, false);
            $this->fail('Pferd oder eigenes Pferd nötig.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_horse_id', $e->errors());
        }
        // Privatpferd nur für die Besitzerin.
        $owner = $this->rider();
        $private = $this->horses()->create($this->organization, ['name' => 'Bella', 'kind' => 'private', 'owner_member_id' => $owner->id, 'requires_clearance' => false, 'rest_minutes' => 0]);
        try {
            $this->horses()->assign($second, $this->rider(), $private, $this->admin);
            $this->fail('Privatpferd nicht für fremde Reiter.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('club_horse_id', $e->errors());
        }
        $this->horses()->assign($second, $owner, $private, $this->admin);
    }

    private function cleared(ClubHorse $horse): ClubMember {
        $rider = $this->rider();
        app(ClubResourceService::class)->grantClearance($horse->resource()->firstOrFail(), $rider, $this->admin);

        return $rider;
    }

    public function test_horse_change_does_not_double_rider_attendance_and_uses_are_tracked_per_horse(): void {
        $a = $this->horse('Anton');
        $b = $this->horse('Berta');
        $lesson = $this->lesson('2026-10-17 10:00', 60);
        $rider = $this->rider();
        app(ClubEventService::class)->register($lesson, $rider, $this->admin, ClubParticipationSource::Leader, null, true);

        $this->horses()->assign($lesson, $rider, $a, $this->admin);
        $this->horses()->recordUse($lesson, $a, $rider, 30, $this->admin);
        $this->horses()->assign($lesson, $rider, $b, $this->admin);
        $this->horses()->recordUse($lesson, $b, $rider, 30, $this->admin);

        $attendance = app(ClubAttendanceService::class);
        $sheet = $attendance->sheetFor($lesson);
        $sheet = $attendance->saveRows($sheet, [$rider->id => ['status' => 'present']], $this->admin, $sheet->version);
        $attendance->confirm($sheet->refresh(), $this->admin, $sheet->version);

        $this->assertSame(60, $attendance->creditableMinutes($rider, CarbonImmutable::parse('2026-10-17'), CarbonImmutable::parse('2026-10-18')), 'Reiteranwesenheit bleibt 60 Minuten.');
        $this->assertSame(2, ClubHorseUse::query()->where('event_id', $lesson->id)->count());
        $this->assertSame(30, $this->horses()->minutesFor($a, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01')));
        $this->assertSame(30, $this->horses()->minutesFor($b, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01')));
        $this->horses()->recordUse($lesson, $b, $rider, 25, $this->admin);
        $this->assertSame(25, $this->horses()->minutesFor($b, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-01')), 'Wiederholtes Erfassen ersetzt.');
    }

    public function test_pages_dialogs_rights_and_portal(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $other = ClubGroup::factory()->create(['name' => 'Fremde Gruppe', 'leader_user_id' => $lead->id]);
        $horse = $this->horse('Fritz');
        $lesson = $this->lesson('2026-10-18 10:00');
        $rider = $this->rider();
        app(ClubEventService::class)->register($lesson, $rider, $this->admin, ClubParticipationSource::Leader, null, true);

        $this->actingAs($this->admin)->get(route('club.horses.index'))->assertOk()->assertSee('Fritz');
        $this->actingAs($this->admin)->get(route('club.horses.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('club.horses.store'), ['name' => 'Gustav', 'kind' => 'school', 'rest_minutes' => 20, 'max_uses_per_day' => 3, 'group_ids' => [$this->group->sqid]])->assertRedirect();
        $gustav = ClubHorse::query()->where('name', 'Gustav')->firstOrFail();
        $this->assertSame(20, $gustav->resource()->firstOrFail()->teardown_minutes);
        $this->assertSame(1, $gustav->groups()->count());
        $this->actingAs($this->admin)->get(route('club.horses.show', $gustav))->assertOk()->assertSee(__('club.horses.card.profile'));
        $this->actingAs($this->admin)->get(route('club.horses.edit', $gustav))->assertOk();
        $this->actingAs($this->admin)->get(route('club.events.show', $lesson))->assertOk()->assertSee(__('club.horses.card.event'))->assertSee($rider->fullName());
        $this->actingAs($this->admin)->post(route('club.events.horses.assign', $lesson), ['club_member_id' => $rider->sqid, 'club_horse_id' => $horse->sqid])->assertRedirect(route('club.events.show', $lesson));
        $assignment = ClubHorseAssignment::query()->where('event_id', $lesson->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('club.events.horses.use', $lesson), ['club_member_id' => $rider->sqid, 'club_horse_id' => $horse->sqid, 'minutes' => 45])->assertRedirect();
        $this->assertSame(45, ClubHorseUse::query()->firstOrFail()->minutes);

        $login = $this->orgUser();
        $rider->update(['user_id' => $login->id]);
        $this->actingAs($login)->get(route('club.my.index'))->assertOk()->assertSee('Fritz');

        $this->actingAs($lead)->get(route('club.horses.index'))->assertOk();
        $this->actingAs($lead)->get(route('club.horses.create'))->assertForbidden();
        $this->actingAs($lead)->post(route('club.events.horses.assign', $lesson), ['club_member_id' => $rider->sqid, 'club_horse_id' => $horse->sqid])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('club.events.horses.unassign', [$lesson, $assignment]))->assertRedirect();
        $this->assertSame(0, ClubHorseAssignment::query()->count());
        $this->actingAs($this->orgUser())->get(route('club.horses.index'))->assertForbidden();
    }
}
