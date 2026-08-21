<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CalDav;

use App\Models\{CalDavConnection, Event, ExternalReference, IntegrationInboxItem};
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Contracts\{CalDavGateway, CalDavGatewayFactory};
use App\Plugins\CalDav\Services\{CalDavCalendarImportService, CalDavEventChange, CalDavSyncPage};
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingCalDavGateway;
use Tests\TestCase;

/**
 * Kalender-Rückimport CalDAV (Feature 121, MVP-610b): sync-collection bzw.
 * ETag-Fallback → NUR Inbox-Fälle. Publish-Echos filtert LAST-MODIFIED
 * gegen den letzten Sync; Serieninstanzen bleiben draußen.
 */
final class CalDavImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function bindGateway(CalDavSyncPage $page): RecordingCalDavGateway {
        $gateway = new RecordingCalDavGateway(syncPage: $page);

        $this->app->instance(CalDavGatewayFactory::class, new class($gateway) implements CalDavGatewayFactory {
            public function __construct(private CalDavGateway $gateway) {}

            public function for(CalDavConnection $connection): CalDavGateway {
                return $this->gateway;
            }
        });

        return $gateway;
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): CalDavConnection {
        return CalDavConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'calendar_path' => 'calendars/team/plan',
            'active' => true,
            'two_way' => true,
        ]);
    }

    private function ics(string $uid, string $summary, ?string $lastModified = null, bool $recurrenceInstance = false, ?string $rrule = null): string {
        $modified = $lastModified ?? Carbon::now()->utc()->format('Ymd\THis\Z');

        return implode("\r\n", array_filter([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Test//EN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            $recurrenceInstance ? 'RECURRENCE-ID:20260820T090000Z' : null,
            'SUMMARY:' . $summary,
            'DTSTART:20260820T090000Z',
            'DTEND:20260820T100000Z',
            'LOCATION:Besprechungsraum 1',
            'ORGANIZER:mailto:kunde@example.test',
            $rrule !== null ? 'RRULE:' . $rrule : null,
            'LAST-MODIFIED:' . $modified,
            'END:VEVENT',
            'END:VCALENDAR',
        ])) . "\r\n";
    }

    private function change(string $objectName, string $ics, string $etag = '"etag-1"'): CalDavEventChange {
        return new CalDavEventChange('/remote.php/dav/calendars/team/plan/' . $objectName, $etag, $ics);
    }

    private function publishedReference(string $objectName, Carbon $syncedAt): ExternalReference {
        $event = Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);

        return ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => CalDavPlugin::ID,
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            'external_id' => $objectName,
            'referenceable_type' => $event->getMorphClass(),
            'referenceable_id' => $event->getKey(),
            'synced_at' => $syncedAt,
        ]);
    }

    public function test_import_stays_off_without_opt_in(): void {
        $connection = $this->connection(['two_way' => false]);
        $gateway = $this->bindGateway(new CalDavSyncPage([$this->change('fremd.ics', $this->ics('fremd', 'Extern'))], [], 'tok'));

        $result = app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame(['proposals' => 0, 'conflicts' => 0, 'deleted' => 0], $result);
        $this->assertSame([], $gateway->seenSyncTokens);
    }

    public function test_external_event_becomes_proposal_and_token_is_kept(): void {
        $connection = $this->connection();
        $gateway = $this->bindGateway(new CalDavSyncPage([$this->change('fremd.ics', $this->ics('fremd', 'Externer Kundentermin'))], [], 'tok-1'));

        $result = app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame(1, $result['proposals']);
        $this->assertSame(0, Event::query()->count()); // nie blinde Anlage
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => CalDavPlugin::ID,
            'dedupe_key' => 'calendar-proposal:fremd.ics',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
        ]);
        $this->assertSame('tok-1', $connection->fresh()?->sync_token);
        $this->assertSame([''], $gateway->seenSyncTokens);

        // Zweiter Lauf mit demselben Objekt: kein zweiter Fall.
        $this->bindGateway(new CalDavSyncPage([$this->change('fremd.ics', $this->ics('fremd', 'Externer Kundentermin'))], [], 'tok-2'));
        $second = app(CalDavCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(0, $second['proposals']);
        $this->assertSame(1, IntegrationInboxItem::query()->count());
    }

    public function test_publish_echo_is_filtered_but_external_edit_conflicts(): void {
        $connection = $this->connection();
        $this->publishedReference('eigen.ics', Carbon::now());

        $this->bindGateway(new CalDavSyncPage([$this->change('eigen.ics', $this->ics('eigen', 'Eigener Termin', Carbon::now()->addSeconds(30)->utc()->format('Ymd\THis\Z')))], [], 't'));
        $echo = app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame(0, $echo['conflicts']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());

        ExternalReference::query()->update(['synced_at' => Carbon::now()->subHour()]);
        $this->bindGateway(new CalDavSyncPage([$this->change('eigen.ics', $this->ics('eigen', 'Extern verschoben'), '"etag-2"')], [], 't2'));
        $conflict = app(CalDavCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $conflict['conflicts']);
        $this->assertDatabaseHas('integration_inbox_items', ['case_type' => IntegrationInboxItem::CASE_CONFLICT]);
    }

    public function test_etag_is_remembered_for_the_fallback_comparison(): void {
        $connection = $this->connection();
        $reference = $this->publishedReference('eigen.ics', Carbon::now());
        $this->bindGateway(new CalDavSyncPage([$this->change('eigen.ics', $this->ics('eigen', 'Eigener Termin'), '"etag-9"')], [], 't'));

        app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame('"etag-9"', (string) ($reference->fresh()?->payload['etag'] ?? ''));

        // Nächster Lauf: der gemerkte ETag geht als lokaler Stand ans Gateway.
        $gateway = $this->bindGateway(new CalDavSyncPage([], [], 't2'));
        app(CalDavCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(['eigen.ics' => '"etag-9"'], $gateway->seenEtags[0]);
    }

    public function test_deleted_published_object_is_reported_but_foreign_one_is_not(): void {
        $connection = $this->connection();
        $this->publishedReference('eigen.ics', Carbon::now()->subHour());
        $this->bindGateway(new CalDavSyncPage([], ['/remote.php/dav/calendars/team/plan/eigen.ics', 'fremd.ics'], 't'));

        $result = app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(1, IntegrationInboxItem::query()->count());
        $this->assertDatabaseHas('integration_inbox_items', ['dedupe_key' => 'calendar-deleted:eigen.ics']);
    }

    public function test_recurrence_instances_are_skipped_and_master_keeps_its_rule(): void {
        $connection = $this->connection();
        $this->bindGateway(new CalDavSyncPage([
            $this->change('instanz.ics', $this->ics('instanz', 'Instanz', null, recurrenceInstance: true)),
            $this->change('serie.ics', $this->ics('serie', 'Serie', null, rrule: 'FREQ=WEEKLY;BYDAY=MO')),
        ], [], 't'));

        $result = app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame(1, $result['proposals']);
        $item = IntegrationInboxItem::query()->firstOrFail();
        $this->assertSame('FREQ=WEEKLY;BYDAY=MO', (string) ($item->mapped_snapshot['recurrence_rule'] ?? ''));
    }

    public function test_unreadable_object_is_ignored(): void {
        $connection = $this->connection();
        $this->bindGateway(new CalDavSyncPage([$this->change('kaputt.ics', 'kein iCalendar')], [], 't'));

        $result = app(CalDavCalendarImportService::class)->run($connection);

        $this->assertSame(0, $result['proposals']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }

    public function test_empty_sync_token_is_not_persisted(): void {
        $connection = $this->connection(['sync_token' => 'alt']);
        $this->bindGateway(new CalDavSyncPage([], [], ''));

        app(CalDavCalendarImportService::class)->run($connection);

        // Server ohne sync-collection: kein Token — dann darf auch keiner
        // stehen bleiben, sonst würde er beim nächsten Lauf mitgeschickt.
        $this->assertNull($connection->fresh()?->sync_token);
        $this->assertNotNull($connection->fresh()?->last_imported_at);
    }

    public function test_command_runs_only_opt_in_connections(): void {
        $this->connection(['two_way' => false]);
        $gateway = $this->bindGateway(new CalDavSyncPage([], [], 't'));

        $this->artisan('caldav:import')->assertExitCode(0);

        $this->assertSame([], $gateway->seenSyncTokens);
    }
}
