<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavSchedulePublishTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CalDav;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{CalDavConnection, Event, ExternalReference, ScheduledShift, User, Vacation};
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Contracts\{CalDavGateway, CalDavGatewayFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingCalDavGateway;
use Tests\TestCase;

/**
 * Feature 058, Rang 17: Publish von Dienstplänen (ScheduledShift) und Urlauben
 * (Vacation) als eigene Kalender-Quelle, gesteuert per Scope je Anbindung.
 */
final class CalDavSchedulePublishTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function bindGateway(): RecordingCalDavGateway {
        $gateway = new RecordingCalDavGateway();

        $this->app->instance(CalDavGatewayFactory::class, new class($gateway) implements CalDavGatewayFactory {
            public function __construct(private CalDavGateway $gateway) {}

            public function for(CalDavConnection $connection): CalDavGateway {
                return $this->gateway;
            }
        });

        return $gateway;
    }

    /**
     * @param  list<string>|null  $scopes
     */
    private function connection(?array $scopes): CalDavConnection {
        return CalDavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'calendar_path' => 'calendars/team/plan',
            'scopes' => $scopes,
            'active' => true,
        ]);
    }

    private function publishedShift(): ScheduledShift {
        return ScheduledShift::factory()->published()->create([
            'organization_id' => $this->organization->id,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    private function approvedVacation(): Vacation {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        return Vacation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
    }

    public function test_schedule_scope_publishes_shift_and_vacation(): void {
        $gateway = $this->bindGateway();
        $this->connection(['schedule']);
        $shift = $this->publishedShift();
        $vacation = $this->approvedVacation();

        $result = (new CalDavPlugin())->publishCalendar($this->organization);

        $this->assertSame(2, $result['published']);
        $this->assertContains('shift-' . $shift->sqid . '.ics', $gateway->puts);
        $this->assertContains('vacation-' . $vacation->sqid . '.ics', $gateway->puts);
        $this->assertSame(2, ExternalReference::query()->where('plugin_id', 'caldav')->count());
    }

    public function test_events_only_connection_ignores_schedule(): void {
        $gateway = $this->bindGateway();
        $this->connection(null); // BC: null = nur events
        $this->publishedShift();
        $this->approvedVacation();
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);

        $result = (new CalDavPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['published']);
        $this->assertSame(['event-' . $event->id . '.ics'], $gateway->puts);
    }

    public function test_schedule_publish_is_idempotent_and_deletes_on_cancel(): void {
        $gateway = $this->bindGateway();
        $this->connection(['schedule']);
        $shift = $this->publishedShift();

        $first = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $first['published']);
        $this->assertSame(['shift-' . $shift->sqid . '.ics'], $gateway->puts);

        // Replay ohne Änderung → unverändert.
        $second = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, $second['published']);
        $this->assertSame(1, $second['unchanged']);

        // Storno → externe Löschung + Referenz entfernt.
        $shift->forceFill(['status' => ScheduledShiftStatus::Cancelled])->save();
        $third = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $third['deleted']);
        $this->assertSame(['shift-' . $shift->sqid . '.ics'], $gateway->deletes);
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', 'caldav')->count());
    }

    public function test_combined_scope_publishes_events_and_schedule(): void {
        $gateway = $this->bindGateway();
        $this->connection(['events', 'schedule']);
        $shift = $this->publishedShift();
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);

        $result = (new CalDavPlugin())->publishCalendar($this->organization);

        $this->assertSame(2, $result['published']);
        $this->assertContains('event-' . $event->id . '.ics', $gateway->puts);
        $this->assertContains('shift-' . $shift->sqid . '.ics', $gateway->puts);
    }
}
