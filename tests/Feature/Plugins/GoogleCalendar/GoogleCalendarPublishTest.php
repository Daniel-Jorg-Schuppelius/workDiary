<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarPublishTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\GoogleCalendar;

use App\Models\{Event, ExternalReference, GoogleCalendarConnection, Organization};
use App\Plugins\GoogleCalendar\Api\GoogleCalendarClient;
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use App\Services\Event\IcsFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-328 (Bauturbo A8): Idempotentes Publish gegen die Google Calendar
 * API v3 — Anlegen mit deterministischer Event-ID aus der stabilen UID,
 * Update statt Duplikat (auch beim 409 nach Referenzverlust), Löschen bei
 * Absage, Ziel-Kalender, Org-Isolation und Auto-Disable-Zählung.
 */
final class GoogleCalendarPublishTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param  array<string, mixed>  $attributes */
    private function connection(array $attributes = []): GoogleCalendarConnection {
        return GoogleCalendarConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => GoogleCalendarConnection::STATUS_ACTIVE,
        ]);
    }

    private function event(): Event {
        return Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);
    }

    public function test_publish_inserts_event_with_deterministic_id_then_unchanged(): void {
        $this->connection();
        $event = $this->event();
        $uid = IcsFeedService::eventUid($event);
        $expectedId = GoogleCalendarClient::eventId($uid);

        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => FakePluginHttp::response(['id' => $expectedId]),
        ]);

        $first = (new GoogleCalendarPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $first['published']);
        $this->assertSame(0, $first['failed']);

        // Insert trägt die deterministische ID aus der stabilen UID.
        $fake->assertSent(function (RequestInterface $request) use ($expectedId): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && str_ends_with((string) $request->getUri()->getPath(), '/calendars/primary/events')
                && ($body['id'] ?? null) === $expectedId;
        });

        $ref = ExternalReference::query()
            ->where('plugin_id', GoogleCalendarPlugin::ID)
            ->where('external_type', RemoteCalendarPublishService::EXTERNAL_TYPE)
            ->firstOrFail();
        $this->assertSame($expectedId, $ref->external_id);
        $this->assertSame($uid, $ref->payload['uid'] ?? null);

        // Replay ohne Änderung → unverändert, kein einziger Request.
        $silent = FakePluginHttp::fake();
        $second = (new GoogleCalendarPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, $second['published']);
        $this->assertSame(1, $second['unchanged']);
        $silent->assertNothingSent();
    }

    public function test_changed_event_is_updated_not_duplicated(): void {
        $this->connection();
        $event = $this->event();
        $expectedId = GoogleCalendarClient::eventId(IcsFeedService::eventUid($event));

        FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => FakePluginHttp::response(['id' => $expectedId]),
        ]);
        (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        // Termin ändern → PUT auf die Remote-ID, kein zweiter Insert.
        $event->forceFill(['title' => 'Neuer Titel'])->save();

        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => FakePluginHttp::response(['id' => $expectedId]),
        ]);
        $result = (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['published']);
        $fake->assertSent(fn(RequestInterface $r): bool => $r->getMethod() === 'PUT'
            && str_contains((string) $r->getUri(), '/events/' . $expectedId));
        $fake->assertNotSent(fn(RequestInterface $r): bool => $r->getMethod() === 'POST');
        $this->assertSame(1, ExternalReference::query()->count());
    }

    public function test_insert_conflict_falls_back_to_update(): void {
        // Referenz verloren (z. B. Alt-Datenbestand), Event existiert remote
        // bereits → 409 beim Insert ⇒ Update statt Duplikat.
        $this->connection();
        $event = $this->event();
        $expectedId = GoogleCalendarClient::eventId(IcsFeedService::eventUid($event));

        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => FakePluginHttp::response(['id' => $expectedId]),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => FakePluginHttp::response(['error' => ['code' => 409]], 409),
        ]);

        $result = (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['published']);
        $this->assertSame(0, $result['failed']);
        $fake->assertSent(fn(RequestInterface $r): bool => $r->getMethod() === 'PUT'
            && str_contains((string) $r->getUri(), '/events/' . $expectedId));
        $this->assertSame($expectedId, ExternalReference::query()->firstOrFail()->external_id);
    }

    public function test_cancelled_event_is_deleted_and_reference_removed(): void {
        $this->connection();
        $event = $this->event();
        $expectedId = GoogleCalendarClient::eventId(IcsFeedService::eventUid($event));

        FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => FakePluginHttp::response(['id' => $expectedId]),
        ]);
        (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        $event->forceFill(['cancelled_at' => now()])->save();

        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/*' => FakePluginHttp::response(null, 204),
        ]);
        $result = (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['deleted']);
        $fake->assertSent(fn(RequestInterface $r): bool => $r->getMethod() === 'DELETE'
            && str_contains((string) $r->getUri(), '/events/' . $expectedId));
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_publish_targets_selected_calendar(): void {
        $this->connection(['calendar_id' => 'team@example.com']);
        $this->event();

        $fake = FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => FakePluginHttp::response(['id' => 'egal']),
        ]);
        $result = (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['published']);
        $fake->assertSent(fn(RequestInterface $r): bool => str_contains((string) $r->getUri(), '/calendars/team%40example.com/events'));
    }

    public function test_publish_is_organization_isolated(): void {
        $this->connection();

        // Termin einer FREMDEN Organisation darf nie publiziert werden.
        $other = Organization::factory()->create();
        Event::factory()->create([
            'organization_id' => $other->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);

        $fake = FakePluginHttp::fake();
        $result = (new GoogleCalendarPlugin())->publishCalendar($this->organization);

        $this->assertSame(0, $result['published']);
        $fake->assertNothingSent();
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_failed_publishes_count_towards_auto_disable(): void {
        // Schwelle für den Test absenken (Settings-Ebene der Organisation).
        $this->organization->forceFill([
            'settings' => ['integrations' => ['auto_disable_threshold' => 2]],
        ])->save();

        $connection = $this->connection();
        $this->event();

        FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => FakePluginHttp::response(['error' => ['code' => 500]], 500),
        ]);

        // 1. Fehl-Lauf → Zähler 1, noch aktiv.
        $first = (new GoogleCalendarPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $first['failed']);
        $connection->refresh();
        $this->assertSame(1, (int) $connection->consecutive_failures);
        $this->assertNull($connection->disabled_at);
        $this->assertTrue($connection->isActive());

        // 2. Fehl-Lauf → Schwelle erreicht, Auto-Disable.
        FakePluginHttp::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => FakePluginHttp::response(['error' => ['code' => 500]], 500),
        ]);
        (new GoogleCalendarPlugin())->publishCalendar($this->organization);
        $connection->refresh();
        $this->assertSame(2, (int) $connection->consecutive_failures);
        $this->assertNotNull($connection->disabled_at);
        $this->assertFalse($connection->isActive());

        // 3. Lauf: deaktivierte Verbindung wird übersprungen — kein Request.
        $silent = FakePluginHttp::fake();
        $third = (new GoogleCalendarPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, array_sum($third));
        $silent->assertNothingSent();
    }
}
