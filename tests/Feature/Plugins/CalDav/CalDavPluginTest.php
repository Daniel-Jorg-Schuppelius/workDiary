<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CalDav;

use App\Models\{CalDavConnection, Event, ExternalReference};
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Contracts\{CalDavGateway, CalDavGatewayFactory};
use App\Plugins\CalDav\Services\CalendarPublishService;
use App\Plugins\Contracts\{CalendarPublisher, PluginCapability};
use App\Plugins\{PluginDiscovery, PluginHealth};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingCalDavGateway;
use Tests\TestCase;

/**
 * Feature 058, MVP-126: Plugin-Verdrahtung. Auto-Discovery, angekündigte
 * CalendarPublish-Fähigkeit, idempotentes Publish echter Events (Anlegen +
 * Löschen bei Absage) und der per-Org-Health-Check über die (gefälschte)
 * Gateway-Factory.
 */
final class CalDavPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function bindGateway(bool $pingOk = true): RecordingCalDavGateway {
        $gateway = new RecordingCalDavGateway(pingOk: $pingOk);

        $this->app->instance(CalDavGatewayFactory::class, new class($gateway) implements CalDavGatewayFactory {
            public function __construct(private CalDavGateway $gateway) {}

            public function for(CalDavConnection $connection): CalDavGateway {
                return $this->gateway;
            }
        });

        return $gateway;
    }

    private function connection(bool $active = true): CalDavConnection {
        return CalDavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'calendar_path' => 'calendars/team/plan',
            'active' => $active,
        ]);
    }

    private function event(): Event {
        return Event::factory()->create([
            'organization_id' => $this->organization->id,
            'started_at' => now()->addDay(),
            'ended_at' => now()->addDay()->addHour(),
        ]);
    }

    public function test_is_discovered_and_announces_calendar_publish(): void {
        $this->assertContains(CalDavPlugin::class, PluginDiscovery::classes());

        $plugin = new CalDavPlugin();
        $this->assertContains(PluginCapability::CalendarPublish, $plugin->capabilities());
        $this->assertTrue($plugin->isPerOrganization());
        $this->assertInstanceOf(CalendarPublisher::class, $plugin);
    }

    public function test_publishes_event_then_deletes_on_cancel(): void {
        $gateway = $this->bindGateway();
        $this->connection();
        $event = $this->event();

        $first = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $first['published']);
        $this->assertSame(['event-' . $event->id . '.ics'], $gateway->puts);
        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', CalendarPublishService::PLUGIN_ID)
            ->count());

        // Replay ohne Änderung → unverändert, kein erneutes PUT.
        $second = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, $second['published']);
        $this->assertSame(1, $second['unchanged']);

        // Absage → Löschung extern + Referenz entfernt.
        $event->forceFill(['cancelled_at' => now()])->save();
        $third = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(1, $third['deleted']);
        $this->assertSame(['event-' . $event->id . '.ics'], $gateway->deletes);
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_publish_skips_inactive_connection(): void {
        $gateway = $this->bindGateway();
        $this->connection(active: false);
        $this->event();

        $result = (new CalDavPlugin())->publishCalendar($this->organization);
        $this->assertSame(0, $result['published']);
        $this->assertSame([], $gateway->puts);
    }

    public function test_health_reflects_ping(): void {
        $this->bindGateway(pingOk: true);
        $this->connection();
        $this->assertTrue((new CalDavPlugin())->healthCheck()->isOk());

        $this->bindGateway(pingOk: false);
        $this->assertTrue((new CalDavPlugin())->healthCheck()->isFailing());
    }

    public function test_health_degraded_without_connection(): void {
        $this->bindGateway();

        $this->assertSame(PluginHealth::STATUS_DEGRADED, (new CalDavPlugin())->healthCheck()->status);
    }
}
