<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{Event, ExternalReference, IntegrationInboxItem, MsgraphConnection, User};
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Services\MsgraphCalendarImportService;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Zwei-Wege-Kalender, erster Schnitt (Feature 102, C3): calendarView-Delta →
 * NUR Inbox-Fälle (Vorschlag/Konflikt/Lösch-Hinweis), nie blinde Anlage;
 * Publish-Echos werden über lastModified≤synced_at+Toleranz gefiltert;
 * Serien-Occurrences bewusst übersprungen; Checkpoint an der Verbindung.
 */
final class MsgraphCalendarImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        config()->set('plugins.msgraph.enabled', true);
        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): MsgraphConnection {
        return MsgraphConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphConnection::STATUS_ACTIVE,
            'two_way' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function remoteEvent(array $overrides = []): array {
        return $overrides + [
            'id' => 'evt-extern',
            'subject' => 'Externer Kundentermin',
            'type' => 'singleInstance',
            'start' => ['dateTime' => '2026-08-20T09:00:00.0000000', 'timeZone' => 'UTC'],
            'end' => ['dateTime' => '2026-08-20T10:00:00.0000000', 'timeZone' => 'UTC'],
            'location' => ['displayName' => 'Besprechungsraum 1'],
            'lastModifiedDateTime' => Carbon::now()->toIso8601String(),
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
            'plugin_id' => MsgraphPlugin::ID,
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            'external_id' => $remoteId,
            'referenceable_type' => $event->getMorphClass(),
            'referenceable_id' => $event->getKey(),
            'synced_at' => $syncedAt,
        ]);
    }

    public function test_external_event_becomes_inbox_proposal_once(): void {
        $connection = $this->connection();
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendarView/delta*' => FakePluginHttp::response([
                'value' => [$this->remoteEvent()],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=final',
            ]),
        ]);

        $result = app(MsgraphCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $result['proposals']);
        $this->assertSame(0, Event::query()->count()); // nie blinde Anlage
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => MsgraphPlugin::ID,
            'dedupe_key' => 'calendar-proposal:evt-extern',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
        ]);
        $this->assertStringContainsString('token=final', (string) $connection->fresh()->calendar_delta_link);

        // Zweiter Lauf über den Checkpoint: kein Duplikat.
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=final' => FakePluginHttp::response([
                'value' => [$this->remoteEvent()],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=final2',
            ]),
        ]);
        $second = app(MsgraphCalendarImportService::class)->run($connection->fresh());
        $this->assertSame(0, $second['proposals']);
        $this->assertSame(1, IntegrationInboxItem::query()->count());
        $fake->assertSent(fn ($request): bool => str_contains((string) $request->getUri(), 'token=final'));
    }

    public function test_publish_echo_is_filtered_but_external_edit_conflicts(): void {
        $connection = $this->connection();

        // Echo: lastModified liegt INNERHALB der Toleranz nach unserem Sync.
        $this->publishedReference('evt-eigen', Carbon::now());
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendarView/delta*' => FakePluginHttp::response([
                'value' => [$this->remoteEvent(['id' => 'evt-eigen', 'lastModifiedDateTime' => Carbon::now()->addSeconds(30)->toIso8601String()])],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=a',
            ]),
        ]);
        $result = app(MsgraphCalendarImportService::class)->run($connection->fresh());
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());

        // Externe Änderung: lastModified deutlich nach dem letzten Sync.
        ExternalReference::query()->update(['synced_at' => Carbon::now()->subHour()]);
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=a' => FakePluginHttp::response([
                'value' => [$this->remoteEvent(['id' => 'evt-eigen', 'subject' => 'Extern verschoben', 'lastModifiedDateTime' => Carbon::now()->toIso8601String()])],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=b',
            ]),
        ]);
        $result = app(MsgraphCalendarImportService::class)->run($connection->fresh());
        $this->assertSame(1, $result['conflicts']);
        $this->assertDatabaseHas('integration_inbox_items', ['case_type' => IntegrationInboxItem::CASE_CONFLICT]);
    }

    public function test_removed_published_event_is_flagged_and_occurrences_skipped(): void {
        $connection = $this->connection();
        $this->publishedReference('evt-geloescht', Carbon::now()->subHour());

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendarView/delta*' => FakePluginHttp::response([
                'value' => [
                    ['id' => 'evt-geloescht', '@removed' => ['reason' => 'deleted']],
                    $this->remoteEvent(['id' => 'evt-serie', 'type' => 'occurrence']),
                ],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?token=x',
            ]),
        ]);

        $result = app(MsgraphCalendarImportService::class)->run($connection->fresh());

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(0, $result['proposals']); // occurrence übersprungen
        $this->assertDatabaseHas('integration_inbox_items', ['dedupe_key' => 'calendar-deleted:evt-geloescht']);
    }

    public function test_command_runs_only_for_two_way_connections(): void {
        $this->connection(['two_way' => false]);
        $idle = FakePluginHttp::fake();

        $this->artisan('msgraph:calendar-import')->assertExitCode(0);
        $idle->assertNothingSent();
    }

    public function test_select_calendar_saves_two_way_and_resets_checkpoint_on_change(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $connection = $this->connection(['calendar_delta_link' => 'https://graph.microsoft.com/v1.0/alt']);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendars*' => FakePluginHttp::response(['value' => [['id' => 'cal-neu', 'name' => 'Projekte']]]),
        ]);

        $this->actingAs($admin)->post(route('admin.msgraph.calendar.store'), [
            'calendar_id' => 'cal-neu',
            'two_way' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $fresh = $connection->fresh();
        $this->assertTrue($fresh->two_way);
        $this->assertNull($fresh->calendar_delta_link); // Kalenderwechsel = Delta-Reset
    }
}
