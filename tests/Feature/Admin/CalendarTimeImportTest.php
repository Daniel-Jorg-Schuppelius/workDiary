<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarTimeImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Import\ImportRunState;
use App\Models\Integration\ImportRun;
use App\Models\Platform\User;
use App\Models\Time\Attendance;
use App\Plugins\CalDav\Contracts\{CalDavGateway, CalDavGatewayFactory};
use App\Plugins\CalDav\Models\CalDavConnection;
use App\Plugins\GoogleCalendar\Models\GoogleCalendarConnection;
use App\Plugins\GoogleCalendar\Services\GoogleCalendarImportFeed;
use App\Plugins\Msgraph\Models\MsgraphConnection;
use App\Plugins\Msgraph\Services\MsgraphCalendarImportFeed;
use App\Support\Sqid;
use CommonToolkit\Parsers\ICalendarParser;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\{FakePluginHttp, RecordingCalDavGateway};
use Tests\TestCase;

/** MVP-976: Zeitimport direkt aus einer verbundenen Kalenderquelle statt Datei-Upload. */
final class CalendarTimeImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function caldav(RecordingCalDavGateway $gateway): CalDavConnection {
        $this->app->instance(CalDavGatewayFactory::class, new class($gateway) implements CalDavGatewayFactory {
            public function __construct(private CalDavGateway $gateway) {}

            public function for(CalDavConnection $connection): CalDavGateway {
                return $this->gateway;
            }
        });

        return CalDavConnection::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Nextcloud', 'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc', 'app_password' => 'secret', 'calendar_path' => 'calendars/team/zeiten', 'active' => true,
        ]);
    }

    public function test_attendances_come_from_the_caldav_calendar_of_the_period(): void {
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'worker@example.com']);
        $gateway = new RecordingCalDavGateway();
        $gateway->rangeObjects = [implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Test//EN',
            'BEGIN:VEVENT', 'UID:tag-1', 'DTSTART:20260701T060000Z', 'DTEND:20260701T140000Z', 'SUMMARY:Baustelle',
            'ORGANIZER:mailto:worker@example.com', 'END:VEVENT', 'END:VCALENDAR',
        ]) . "\r\n"];
        $connection = $this->caldav($gateway);
        $source = 'caldav:' . Sqid::encode(CalDavConnection::class, $connection->id);

        $this->actingAs($this->admin)->get(route('admin.imports.create', ['entity' => 'attendances']))->assertOk()
            ->assertSeeText(__('import.upload.calendar'))->assertSee($source, false);

        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), ['entity' => 'attendances', 'calendar' => $source])
            ->assertSessionHasErrors(['ical_recurrence_from', 'ical_recurrence_until']);

        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => 'attendances', 'calendar' => $source, 'ical_recurrence_from' => '2026-07-01', 'ical_recurrence_until' => '2026-07-31',
        ])->assertRedirect();
        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunState::AwaitingApproval, $run->state);
        $this->actingAs($this->admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        $this->assertSame('2026-07-01', Attendance::query()->sole()->date->format('Y-m-d'));
    }

    public function test_unknown_connection_is_reported_without_a_run(): void {
        $this->actingAs($this->admin)->post(route('admin.imports.preflight'), [
            'entity' => 'attendances', 'calendar' => 'caldav:xyz', 'ical_recurrence_from' => '2026-07-01', 'ical_recurrence_until' => '2026-07-31',
        ])->assertSessionHasErrors(['calendar' => __('import.error.calendar.fetch')]);
        $this->assertSame(0, ImportRun::query()->count());
    }

    public function test_google_feed_writes_single_events_with_the_own_attendee(): void {
        config()->set('plugins.google_calendar.enabled', true);
        $connection = GoogleCalendarConnection::query()->create(['organization_id' => $this->organization->id, 'access_token' => 't', 'status' => GoogleCalendarConnection::STATUS_ACTIVE]);
        FakePluginHttp::fake(['https://www.googleapis.com/calendar/v3/calendars/primary/events*' => FakePluginHttp::response(['items' => [
            ['id' => 'e1', 'iCalUID' => 'e1@google.com', 'status' => 'confirmed', 'summary' => 'Montage, Halle 2', 'transparency' => 'transparent',
                'start' => ['dateTime' => '2026-07-02T08:00:00+02:00'], 'end' => ['dateTime' => '2026-07-02T12:00:00+02:00'],
                'organizer' => ['email' => 'chef@example.com'], 'attendees' => [['email' => 'worker@example.com', 'self' => true]]],
            ['id' => 'e2', 'status' => 'cancelled', 'start' => ['dateTime' => '2026-07-03T08:00:00+02:00']],
        ]])]);

        $ics = app(GoogleCalendarImportFeed::class)->fetch($this->organization, Sqid::encode(GoogleCalendarConnection::class, $connection->id), new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-07-31'));

        $events = ICalendarParser::fromString($ics)->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('Montage, Halle 2', $events[0]->getSummary());
        $this->assertSame('worker@example.com', $events[0]->getOrganizer());
        $this->assertTrue($events[0]->isTransparent());
        $this->assertSame('2026-07-02 06:00', $events[0]->getStart(new \DateTimeZone('UTC'))?->format('Y-m-d H:i'));
    }

    public function test_microsoft_feed_keeps_categories_and_skips_cancelled(): void {
        $this->pluginSecret('msgraph', ['enabled' => true, 'client_id' => 'c', 'client_secret' => 's']);
        $connection = MsgraphConnection::query()->create(['organization_id' => $this->organization->id, 'access_token' => 't', 'status' => MsgraphConnection::STATUS_ACTIVE]);
        FakePluginHttp::fake(['https://graph.microsoft.com/v1.0/me/calendarView*' => FakePluginHttp::response(['value' => [
            ['id' => 'o1', 'subject' => 'Einsatz', 'categories' => ['Arbeitszeit'], 'showAs' => 'busy',
                'start' => ['dateTime' => '2026-07-02T06:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-07-02T14:00:00.0000000', 'timeZone' => 'UTC'],
                'organizer' => ['emailAddress' => ['address' => 'worker@example.com']]],
            ['id' => 'o2', 'subject' => 'Abgesagt', 'isCancelled' => true, 'start' => ['dateTime' => '2026-07-03T06:00:00.0000000', 'timeZone' => 'UTC']],
        ]])]);

        $ics = app(MsgraphCalendarImportFeed::class)->fetch($this->organization, Sqid::encode(MsgraphConnection::class, $connection->id), new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-07-31'));

        $events = ICalendarParser::fromString($ics)->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(['Arbeitszeit'], $events[0]->getCategories());
        $this->assertFalse($events[0]->isTransparent());
    }
}
