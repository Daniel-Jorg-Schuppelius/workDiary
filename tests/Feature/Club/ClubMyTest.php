<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubEventVisibility, ClubParticipationSource, ClubParticipationStatus};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Calendar\Event;
use App\Models\Club\{ClubEventParticipation, ClubGroup, ClubMember, ClubNotification};
use App\Models\Notification\NotificationRule;
use App\Models\Platform\User;
use App\Notifications\GenericEventNotification;
use App\Services\Club\{ClubAttendanceService, ClubEventService, ClubGroupService, ClubMemberService, ClubPortalContext};
use App\Services\Club\DeadlineScans\ClubEventReminderScan;
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * „Mein Verein“ und Benachrichtigungen (Feature 159, MVP-845): passender
 * Lehrgang ohne persönliche Einladung, Selbst- und Vertretungsanmeldung,
 * Widerruf beendet den Zugriff, Zustellung ohne Doppelmeldung an Konto und
 * Mailkontakte, Erinnerung, Verschiebung, Absage und Nachrücken.
 */
class ClubMyTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        // Determinismus: In-App-Kanal für Kontoempfänger, kein Mail-Rendering.
        foreach (NotificationEvent::cases() as $event) {
            if (str_starts_with($event->value, 'club.')) {
                NotificationRule::factory()->forEvent($event)->create([
                    'organization_id' => $this->organization->id,
                    'channels' => [NotificationChannel::InApp->value],
                    'notify_affected' => true,
                    'recipient_roles' => [],
                ]);
            }
        }
    }

    private function clubEvents(): ClubEventService {
        return app(ClubEventService::class);
    }

    /** @param list<int> $groupIds
     * @param array<string, mixed> $overrides */
    private function makeEvent(array $groupIds, ?CarbonImmutable $start = null, array $overrides = []): Event {
        $start ??= CarbonImmutable::now()->addDays(3)->setTime(18, 0);

        return $this->clubEvents()->create($this->organization, $this->admin, array_merge([
            'title' => 'Lehrgang',
            'kind' => 'course',
            'visibility' => ClubEventVisibility::Groups->value,
            'club_group_ids' => $groupIds,
            'started_at' => $start->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $start->addHours(2)->utc()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Berlin',
        ], $overrides));
    }

    private function memberIn(ClubGroup $group, ?User $user = null, ?string $email = null): ClubMember {
        $member = ClubMember::factory()->aged(14)->create(['user_id' => $user?->id, 'email' => $email]);
        app(ClubGroupService::class)->admit($group, $member, CarbonImmutable::today()->subMonth(), $this->admin);

        return $member;
    }

    /** @return list<string> Ereignisse der In-App-Benachrichtigungen einer Person */
    private function eventsOf(User $user): array {
        return $user->notifications()->get()->map(fn($n): string => (string) (((array) $n->data)['event'] ?? ''))->sort()->values()->all();
    }

    // ── Sichtbarkeit und Anmeldung ───────────────────────────────────────

    public function test_member_finds_a_course_of_the_own_group_without_invitation_and_registers(): void {
        $group = ClubGroup::factory()->create();
        $other = ClubGroup::factory()->create();
        $self = $this->orgUser();
        $member = $this->memberIn($group, $self);
        $foreign = $this->memberIn($other);
        $course = $this->makeEvent([$group->id]);
        $otherCourse = $this->makeEvent([$other->id], null, ['title' => 'Fremder Lehrgang']);
        $invitedOnly = $this->makeEvent([], null, ['title' => 'Nur eingeladen', 'visibility' => ClubEventVisibility::Invited->value]);

        $page = $this->actingAs($self)->get(route('club.my.index'));
        $page->assertOk()->assertSee('Lehrgang')->assertDontSee('Fremder Lehrgang')->assertDontSee('Nur eingeladen')->assertDontSee($foreign->last_name);

        $this->actingAs($self)->post(route('club.my.register', $course))->assertRedirect(route('club.my.index'));
        $participation = ClubEventParticipation::query()->where('event_id', $course->id)->firstOrFail();
        $this->assertSame(ClubParticipationStatus::Registered, $participation->status);
        $this->assertSame(ClubParticipationSource::Self, $participation->source);
        $this->assertSame($member->id, $participation->club_member_id);

        $this->actingAs($self)->post(route('club.my.register', $otherCourse))->assertSessionHasErrors('club_member_id');
        $this->actingAs($self)->post(route('club.my.register', $invitedOnly))->assertSessionHasErrors('club_member_id');

        $this->actingAs($self)->post(route('club.my.cancel', $course))->assertRedirect(route('club.my.index'));
        $this->assertSame(ClubParticipationStatus::Cancelled, $participation->refresh()->status);

        $this->assertSame([], $this->eventsOf($self), 'Eigene An-/Abmeldung erzeugt keine Nachricht.');
        $this->actingAs($this->orgUser())->get(route('club.my.index'))->assertForbidden();
    }

    public function test_guardian_selects_the_child_and_revocation_ends_access(): void {
        $group = ClubGroup::factory()->create();
        $parent = $this->orgUser();
        $child = $this->memberIn($group);
        $second = $this->memberIn($group);
        $guardian = app(ClubMemberService::class)->addGuardian($child, ['name' => 'Elternteil', 'user_id' => $parent->id, 'permissions' => ['register', 'view_attendance']], $this->admin);
        app(ClubMemberService::class)->addGuardian($second, ['name' => 'Elternteil', 'user_id' => $parent->id, 'permissions' => ['view_attendance']], $this->admin);
        $course = $this->makeEvent([$group->id]);

        $this->actingAs($parent)->get(route('club.my.index'))->assertOk()->assertSee($child->fullName())->assertSee('name="club_member_id"', false);
        $this->actingAs($parent)->post(route('club.my.register', $course))->assertRedirect(route('club.my.index'));
        $participation = ClubEventParticipation::query()->where('event_id', $course->id)->firstOrFail();
        $this->assertSame(ClubParticipationSource::Guardian, $participation->source);
        $this->assertSame($guardian->id, $participation->club_guardian_id);

        $this->actingAs($parent)->post(route('club.my.select'), ['club_member_id' => $second->sqid])->assertRedirect(route('club.my.index'));
        $this->assertSame($second->id, app(ClubPortalContext::class)->current($parent)?->member->id);
        $this->actingAs($parent)->post(route('club.my.register', $course))->assertForbidden();
        $this->actingAs($parent)->get(route('club.my.attendance'))->assertOk();

        app(ClubMemberService::class)->revokeGuardian($guardian, $this->admin);
        $this->actingAs($parent)->post(route('club.my.select'), ['club_member_id' => $child->sqid])->assertNotFound();
        app(ClubMemberService::class)->revokeGuardian($second->guardians()->firstOrFail(), $this->admin);
        $this->actingAs($parent)->get(route('club.my.index'))->assertForbidden();
        $this->actingAs($parent)->post(route('club.my.register', $course))->assertForbidden();
    }

    public function test_attendance_view_shows_only_confirmed_own_records(): void {
        $group = ClubGroup::factory()->create();
        $self = $this->orgUser();
        $member = $this->memberIn($group, $self);
        $start = CarbonImmutable::parse('2026-09-10 18:00', 'Europe/Berlin');
        $event = $this->makeEvent([$group->id], $start, ['title' => 'Training']);
        $attendance = app(ClubAttendanceService::class);
        $sheet = $attendance->sheetFor($event);
        $sheet = $attendance->saveRows($sheet, [$member->id => ['status' => 'partial', 'minutes' => 75]], $this->admin, 0);

        $session = $this->dateRangeSession('2026-09-01', '2026-09-30');
        $this->actingAs($self)->withSession($session)->get(route('club.my.attendance'))->assertOk()->assertDontSee('Training');
        $attendance->confirm($sheet, $this->admin, $sheet->version);
        $this->actingAs($self)->withSession($session)->get(route('club.my.attendance'))->assertOk()->assertSee('Training')->assertSee('75');
        $this->actingAs($self)->withSession($session)->get(route('club.my.index'))->assertOk()->assertSee('1:15');
    }

    // ── Benachrichtigungen ───────────────────────────────────────────────

    public function test_reschedule_and_cancellation_notify_once_per_change(): void {
        $group = ClubGroup::factory()->create();
        $self = $this->orgUser();
        $member = $this->memberIn($group, $self);
        $event = $this->makeEvent([$group->id]);
        $this->clubEvents()->register($event, $member, $this->admin, ClubParticipationSource::Admin);
        $newStart = CarbonImmutable::instance($event->started_at)->addHour();
        $data = ['kind' => 'course', 'visibility' => ClubEventVisibility::Groups->value, 'started_at' => $newStart->format('Y-m-d H:i:s'), 'ended_at' => $newStart->addHours(2)->format('Y-m-d H:i:s'), 'club_group_ids' => [$group->id]];

        $this->clubEvents()->update($event, $this->admin, ['title' => 'Lehrgang'] + $data);
        $this->clubEvents()->update($event->refresh(), $this->admin, ['title' => 'Lehrgang'] + $data);
        $this->assertSame([NotificationEvent::ClubEventRescheduled->value], $this->eventsOf($self), 'Gleiche Zeiten erneut speichern meldet nicht noch einmal.');
        $this->assertSame(1, ClubNotification::query()->where('kind', ClubNotification::KIND_RESCHEDULED)->count());

        $this->clubEvents()->cancel($event->refresh(), $this->admin, 'Halle gesperrt');
        $this->clubEvents()->cancel($event->refresh(), $this->admin, 'Halle gesperrt');
        $this->assertSame([NotificationEvent::ClubEventCancelled->value, NotificationEvent::ClubEventRescheduled->value], $this->eventsOf($self));

        $this->actingAs($self)->get(route('club.my.index'))->assertOk()->assertSee('Lehrgang');
        $this->actingAs($this->admin)->get(route('club.events.show', $event))->assertOk()->assertSee(__('club.my.card.deliveries'));
    }

    public function test_waitlist_promotion_and_reminder_reach_account_and_mail_contacts_once(): void {
        Notification::fake();
        $group = ClubGroup::factory()->create();
        $self = $this->orgUser();
        $first = $this->memberIn($group, $self);
        $waiting = $this->memberIn($group, null, 'wartend@example.test');
        app(ClubMemberService::class)->addGuardian($waiting, ['name' => 'Elternteil', 'email' => 'eltern@example.test', 'permissions' => ['receive_messages']], $this->admin);
        app(ClubMemberService::class)->addGuardian($waiting, ['name' => 'Ohne Recht', 'email' => 'stumm@example.test', 'permissions' => ['register']], $this->admin);
        $event = $this->makeEvent([$group->id], CarbonImmutable::now()->addHours(6), ['max_participants' => 1]);
        $this->clubEvents()->register($event, $first, $this->admin, ClubParticipationSource::Admin);
        $this->clubEvents()->register($event, $waiting, $this->admin, ClubParticipationSource::Admin);

        $this->clubEvents()->cancelRegistration($event, $first, $this->admin, true);
        $mails = fn(): array => ClubNotification::query()->where('kind', ClubNotification::KIND_PROMOTED)->pluck('recipient')->sort()->values()->all();
        $this->assertSame(['mail:eltern@example.test', 'mail:wartend@example.test'], $mails(), 'Mitglied ohne Konto und Vertretung mit Recht; stumme Vertretung nicht.');
        Notification::assertSentOnDemand(GenericEventNotification::class, fn(GenericEventNotification $n, array $channels, AnonymousNotifiable $notifiable): bool => $n->event === NotificationEvent::ClubWaitlistPromoted && ($notifiable->routes['mail'] ?? null) === 'wartend@example.test');
        Notification::assertCount(2);

        $options = new DeadlineScanOptions(7, 30);
        $scan = app(ClubEventReminderScan::class);
        $this->assertSame(2, $scan->run(app(NotificationDispatcher::class), $options), 'Erinnerung an den (nachgerückten) Angemeldeten: Mitglied + Vertretung.');
        $this->assertSame(0, $scan->run(app(NotificationDispatcher::class), $options), 'Zweiter Lauf verdoppelt nichts.');
        $this->assertSame(2, ClubNotification::query()->where('kind', ClubNotification::KIND_REMINDER)->count());
        $this->assertSame(0, ClubNotification::query()->where('club_member_id', $first->id)->count(), 'Abgemeldete werden nicht erinnert.');
    }
}
