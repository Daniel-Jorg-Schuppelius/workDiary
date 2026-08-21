<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\GoogleCalendar;

use App\Models\{Event, ExternalReference, GoogleCalendarConnection, IntegrationInboxItem};
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\GoogleCalendar\Services\GoogleCalendarImportService;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Kalender-Rückimport Google (Feature 121, MVP-610a): Änderungsliste →
 * NUR Inbox-Fälle, nie blinde Anlage. Publish-Echos werden über
 * `updated` ≤ `synced_at` + Toleranz gefiltert; Serieninstanzen bleiben
 * bewusst draußen; der `syncToken` ist der Wiederanlaufpunkt.
 */
final class GoogleCalendarImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const EVENTS = 'https://www.googleapis.com/calendar/v3/calendars/primary/events*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('plugins.google_calendar.enabled', true);
        config()->set('plugins.google_calendar.client_id', 'test-client');
        config()->set('plugins.google_calendar.client_secret', 'test-secret');
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): GoogleCalendarConnection {
        return GoogleCalendarConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => GoogleCalendarConnection::STATUS_ACTIVE,
            'two_way' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function remoteEvent(array $overrides = []): array {
        return $overrides + [
            'id' => 'evt-extern',
            'status' => 'confirmed',
            'summary' => 'Externer Kundentermin',
            'start' => ['dateTime' => '2026-08-20T09:00:00+02:00', 'timeZone' => 'Europe/Berlin'],
            'end' => ['dateTime' => '2026-08-20T10:00:00+02:00', 'timeZone' => 'Europe/Berlin'],
            'location' => 'Besprechungsraum 1',
            'organizer' => ['email' => 'kunde@example.test'],
            'updated' => Carbon::now()->toIso8601String(),
        ];
    }

    private function publishedReference(string $remoteId, Carbon $syncedAt): ExternalReference {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);

        return ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GoogleCalendarPlugin::ID,
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            'external_id' => $remoteId,
            'referenceable_type' => $event->getMorphClass(),
            'referenceable_id' => $event->getKey(),
            'synced_at' => $syncedAt,
        ]);
    }

    public function test_import_stays_off_without_opt_in(): void {
        $connection = $this->connection(['two_way' => false]);
        $fake = FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response(['items' => []])]);

        $result = app(GoogleCalendarImportService::class)->run($connection);

        $this->assertSame(['proposals' => 0, 'conflicts' => 0, 'deleted' => 0], $result);
        $fake->assertNothingSent();
    }

    public function test_external_event_becomes_inbox_proposal_once(): void {
        $connection = $this->connection();
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent()],
            'nextSyncToken' => 'token-final',
        ])]);

        $result = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $result['proposals']);
        $this->assertSame(0, Event::query()->count()); // nie blinde Anlage
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => GoogleCalendarPlugin::ID,
            'dedupe_key' => 'calendar-proposal:evt-extern',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
        ]);
        $this->assertSame('token-final', $connection->fresh()?->sync_token);

        // Zweiter Lauf: derselbe Termin ergibt keinen zweiten Fall.
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent()],
            'nextSyncToken' => 'token-2',
        ])]);
        $second = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(0, $second['proposals']);
        $this->assertSame(1, IntegrationInboxItem::query()->count());
    }

    public function test_publish_echo_is_filtered_but_external_edit_conflicts(): void {
        $connection = $this->connection();
        $this->publishedReference('evt-eigen', Carbon::now());

        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent(['id' => 'evt-eigen', 'updated' => Carbon::now()->addSeconds(30)->toIso8601String()])],
            'nextSyncToken' => 'a',
        ])]);
        $echo = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(0, $echo['conflicts']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());

        ExternalReference::query()->update(['synced_at' => Carbon::now()->subHour()]);
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent(['id' => 'evt-eigen', 'summary' => 'Extern verschoben'])],
            'nextSyncToken' => 'b',
        ])]);
        $conflict = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $conflict['conflicts']);
        $this->assertDatabaseHas('integration_inbox_items', ['case_type' => IntegrationInboxItem::CASE_CONFLICT]);
    }

    public function test_cancelled_published_event_is_reported_but_foreign_one_is_not(): void {
        $connection = $this->connection();
        $this->publishedReference('evt-eigen', Carbon::now()->subHour());

        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [
                $this->remoteEvent(['id' => 'evt-eigen', 'status' => 'cancelled']),
                $this->remoteEvent(['id' => 'evt-fremd', 'status' => 'cancelled']),
            ],
            'nextSyncToken' => 'c',
        ])]);

        $result = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(1, IntegrationInboxItem::query()->count());
        $this->assertDatabaseHas('integration_inbox_items', ['dedupe_key' => 'calendar-deleted:evt-eigen']);
    }

    public function test_recurring_instances_are_skipped(): void {
        $connection = $this->connection();
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent(['id' => 'evt-instanz', 'recurringEventId' => 'evt-serie'])],
            'nextSyncToken' => 'd',
        ])]);

        $result = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(0, $result['proposals']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }

    public function test_series_master_carries_the_recurrence_rule(): void {
        $connection = $this->connection();
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent(['id' => 'evt-serie', 'recurrence' => ['RRULE:FREQ=WEEKLY;BYDAY=MO']])],
            'nextSyncToken' => 'e',
        ])]);

        app(GoogleCalendarImportService::class)->run($connection->fresh());

        $item = IntegrationInboxItem::query()->firstOrFail();
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO', (string) ($item->mapped_snapshot['recurrence_rule'] ?? ''));
    }

    public function test_stale_sync_token_triggers_exactly_one_full_pass(): void {
        $connection = $this->connection(['sync_token' => 'abgelaufen']);
        $calls = 0;
        FakePluginHttp::fake([self::EVENTS => function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? FakePluginHttp::response(['error' => 'stale'], 410)
                : FakePluginHttp::response(['items' => [$this->remoteEvent()], 'nextSyncToken' => 'frisch']);
        }]);

        $result = app(GoogleCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $result['proposals']);
        $this->assertSame(2, $calls);
        $this->assertSame('frisch', $connection->fresh()?->sync_token);
    }

    public function test_all_day_event_is_mapped_as_all_day(): void {
        $connection = $this->connection();
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response([
            'items' => [$this->remoteEvent([
                'id' => 'evt-ganztags',
                'start' => ['date' => '2026-08-20'],
                'end' => ['date' => '2026-08-21'],
            ])],
            'nextSyncToken' => 'f',
        ])]);

        app(GoogleCalendarImportService::class)->run($connection->fresh());

        $item = IntegrationInboxItem::query()->firstOrFail();
        $this->assertTrue((bool) ($item->mapped_snapshot['is_all_day'] ?? false));
        $this->assertSame('2026-08-20 00:00:00', (string) ($item->mapped_snapshot['started_at'] ?? ''));
    }

    public function test_command_runs_only_opt_in_connections(): void {
        $this->connection(['two_way' => false]);
        FakePluginHttp::fake([self::EVENTS => FakePluginHttp::response(['items' => []])]);

        $this->artisan('google-calendar:import')->assertExitCode(0);

        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }
}
