<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphPublishTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{Event, ExternalReference, MsgraphConnection, Organization};
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use App\Services\Event\IcsFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-328 (Bauturbo A8): Idempotentes Publish gegen Microsoft Graph —
 * Anlegen mit stabiler UID (`transactionId`), Update statt Duplikat beim
 * geänderten Termin, Löschen bei Absage, Org-Isolation und
 * Auto-Disable-Zählung über die einheitliche Verbindungs-Gesundheit.
 */
final class MsgraphPublishTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param  array<string, mixed>  $attributes */
    private function connection(array $attributes = []): MsgraphConnection {
        return MsgraphConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-123',
            'status' => MsgraphConnection::STATUS_ACTIVE,
        ]);
    }

    private function event(): Event {
        return Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);
    }

    public function test_publish_creates_event_with_stable_uid_then_unchanged(): void {
        $this->connection();
        $event = $this->event();
        $uid = IcsFeedService::eventUid($event);

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['id' => 'AAMk-1'], 201),
        ]);

        $first = (new MsgraphPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $first['published']);
        $this->assertSame(0, $first['failed']);

        // Create trägt die stabile UID als transactionId (Graph-Idempotenz).
        $fake->assertSent(function (RequestInterface $request) use ($uid): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && str_ends_with((string) $request->getUri()->getPath(), '/me/events')
                && ($body['transactionId'] ?? null) === $uid;
        });

        $ref = ExternalReference::query()
            ->where('plugin_id', MsgraphPlugin::ID)
            ->where('external_type', RemoteCalendarPublishService::EXTERNAL_TYPE)
            ->firstOrFail();
        $this->assertSame('AAMk-1', $ref->external_id);
        $this->assertSame($uid, $ref->payload['uid'] ?? null);

        // Replay ohne Änderung → unverändert, kein einziger Request.
        $silent = FakePluginHttp::fake();
        $second = (new MsgraphPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, $second['published']);
        $this->assertSame(1, $second['unchanged']);
        $silent->assertNothingSent();
    }

    public function test_changed_event_is_updated_not_duplicated(): void {
        $this->connection();
        $event = $this->event();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['id' => 'AAMk-1'], 201),
        ]);
        (new MsgraphPlugin())->publishCalendar($this->organization);

        // Termin ändern → PATCH auf die Remote-ID, kein zweiter Create.
        $event->forceFill(['title' => 'Neuer Titel'])->save();

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events/*' => FakePluginHttp::response(['id' => 'AAMk-1']),
        ]);
        $result = (new MsgraphPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['published']);
        $fake->assertSent(fn(RequestInterface $r): bool => $r->getMethod() === 'PATCH'
            && str_contains((string) $r->getUri(), '/me/events/AAMk-1'));
        $fake->assertNotSent(fn(RequestInterface $r): bool => $r->getMethod() === 'POST');
        $this->assertSame(1, ExternalReference::query()->count());
    }

    public function test_cancelled_event_is_deleted_and_reference_removed(): void {
        $this->connection();
        $event = $this->event();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['id' => 'AAMk-1'], 201),
        ]);
        (new MsgraphPlugin())->publishCalendar($this->organization);

        $event->forceFill(['cancelled_at' => now()])->save();

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events/*' => FakePluginHttp::response(null, 204),
        ]);
        $result = (new MsgraphPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['deleted']);
        $fake->assertSent(fn(RequestInterface $r): bool => $r->getMethod() === 'DELETE'
            && str_contains((string) $r->getUri(), '/me/events/AAMk-1'));
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_publish_targets_selected_calendar(): void {
        $this->connection(['calendar_id' => 'cal-abc']);
        $this->event();

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendars/cal-abc/events' => FakePluginHttp::response(['id' => 'AAMk-9'], 201),
        ]);
        $result = (new MsgraphPlugin())->publishCalendar($this->organization);

        $this->assertSame(1, $result['published']);
        $fake->assertSent(fn(RequestInterface $r): bool => str_contains((string) $r->getUri(), '/me/calendars/cal-abc/events'));
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
        $result = (new MsgraphPlugin())->publishCalendar($this->organization);

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
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['error' => ['code' => 'InternalServerError']], 500),
        ]);

        // 1. Fehl-Lauf → Zähler 1, noch aktiv.
        $first = (new MsgraphPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $first['failed']);
        $connection->refresh();
        $this->assertSame(1, (int) $connection->consecutive_failures);
        $this->assertNull($connection->disabled_at);
        $this->assertTrue($connection->isActive());

        // 2. Fehl-Lauf → Schwelle erreicht, Auto-Disable.
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/events' => FakePluginHttp::response(['error' => ['code' => 'InternalServerError']], 500),
        ]);
        (new MsgraphPlugin())->publishCalendar($this->organization);
        $connection->refresh();
        $this->assertSame(2, (int) $connection->consecutive_failures);
        $this->assertNotNull($connection->disabled_at);
        $this->assertFalse($connection->isActive());

        // 3. Lauf: deaktivierte Verbindung wird übersprungen — kein Request.
        $silent = FakePluginHttp::fake();
        $third = (new MsgraphPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, array_sum($third));
        $silent->assertNothingSent();
    }
}
