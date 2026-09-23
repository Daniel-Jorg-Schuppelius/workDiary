<?php

/*
 * Filename     : OpenAttendanceReminderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Attendance;

use App\Enums\Notification\NotificationEvent;
use App\Models\Attendance;
use App\Models\Platform\User;
use App\Notifications\GenericEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abend-Erinnerung bei offener Stempelung (MVP-803, Vollscan-Entscheid P2-04):
 * Vorher schloss erst das System am Folgetag — die Person erfuhr nie, dass sie
 * das Ausstempeln vergessen hatte.
 */
final class OpenAttendanceReminderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow(Carbon::parse('2026-09-17 19:00:00'));
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_long_open_clock_in_is_reminded_once(): void {
        Notification::fake();
        $forgotten = $this->openSince('2026-09-17 07:30:00');

        $this->artisan('attendance:remind-open')->expectsOutputToContain('Erinnerungen versendet: 1')->assertSuccessful();
        $this->artisan('attendance:remind-open')->expectsOutputToContain('Erinnerungen versendet: 0')->assertSuccessful();

        Notification::assertSentToTimes($forgotten->user, GenericEventNotification::class, 1);
        Notification::assertSentTo(
            $forgotten->user,
            GenericEventNotification::class,
            fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::AttendanceOpenReminder,
        );
    }

    public function test_evening_shift_and_deactivated_accounts_are_not_reminded(): void {
        Notification::fake();
        $eveningShift = $this->openSince('2026-09-17 17:45:00');
        $former = $this->openSince('2026-09-17 06:00:00');
        $former->user->forceFill(['deactivated_at' => now()])->save();

        $this->artisan('attendance:remind-open')->expectsOutputToContain('Erinnerungen versendet: 0')->assertSuccessful();

        Notification::assertNotSentTo($eveningShift->user, GenericEventNotification::class);
        Notification::assertNotSentTo($former->user, GenericEventNotification::class);
    }

    public function test_the_reminder_is_scheduled_in_the_evening(): void {
        $job = (array) config('scheduler.jobs')['attendance.open_reminder'];

        $this->assertSame('attendance:remind-open', $job['command']);
        $this->assertSame(['type' => 'dailyAt', 'time' => '19:00'], $job['cadence']);
    }

    private function openSince(string $startedAt): Attendance {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        return Attendance::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'started_at' => Carbon::parse($startedAt),
            'status' => 'open',
            'source' => 'clock',
        ])->load('user');
    }
}
