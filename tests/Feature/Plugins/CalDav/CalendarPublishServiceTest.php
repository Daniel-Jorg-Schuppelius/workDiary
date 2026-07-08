<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarPublishServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CalDav;

use App\Models\{CalDavConnection, ExternalReference};
use App\Plugins\CalDav\Services\{CalendarPublishItem, CalendarPublishService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingCalDavGateway;
use Tests\TestCase;

/**
 * Feature 058, MVP-126: idempotenter CalDAV-Abgleich. Prüft PUT bei Neu/Änderung,
 * Überspringen bei unverändertem Hash, DELETE bei Absage, Wiederanlauf bei
 * Gateway-Fehler und den fortgeschriebenen Publish-Zeitpunkt.
 */
final class CalendarPublishServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function connection(): CalDavConnection {
        return CalDavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'calendar_path' => 'calendars/team/plan',
            'active' => true,
        ]);
    }

    private function item(int $id, string $ics = 'ICS-A', bool $cancelled = false): CalendarPublishItem {
        return new CalendarPublishItem(
            uid: "event-{$id}@workdiary",
            objectName: "event-{$id}.ics",
            ics: $cancelled ? '' : $ics,
            referenceableType: 'App\\Models\\Event',
            referenceableId: $id,
            cancelled: $cancelled,
        );
    }

    private function gateway(): RecordingCalDavGateway {
        return new RecordingCalDavGateway();
    }

    public function test_new_item_is_put_and_referenced(): void {
        $connection = $this->connection();
        $gateway = $this->gateway();

        $result = (new CalendarPublishService())->publish($connection, $gateway, [$this->item(1)]);

        $this->assertSame(['published' => 1, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0], $result);
        $this->assertSame(['event-1.ics'], $gateway->puts);
        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', CalendarPublishService::PLUGIN_ID)
            ->where('external_type', CalendarPublishService::EXTERNAL_TYPE)
            ->count());
        $connection->refresh();
        $this->assertNotNull($connection->last_published_at);
    }

    public function test_unchanged_item_is_skipped_on_replay(): void {
        $connection = $this->connection();
        $service = new CalendarPublishService();

        $service->publish($connection, $this->gateway(), [$this->item(1, 'SAME')]);
        $gateway = $this->gateway();
        $result = $service->publish($connection, $gateway, [$this->item(1, 'SAME')]);

        $this->assertSame(1, $result['unchanged']);
        $this->assertSame(0, $result['published']);
        $this->assertSame([], $gateway->puts); // kein erneutes PUT
    }

    public function test_changed_item_is_published_again(): void {
        $connection = $this->connection();
        $service = new CalendarPublishService();

        $service->publish($connection, $this->gateway(), [$this->item(1, 'V1')]);
        $gateway = $this->gateway();
        $result = $service->publish($connection, $gateway, [$this->item(1, 'V2')]);

        $this->assertSame(1, $result['published']);
        $this->assertSame(['event-1.ics'], $gateway->puts);
        $this->assertSame(1, ExternalReference::query()->count()); // dieselbe Referenz, aktualisiert
    }

    public function test_cancelled_item_deletes_object_and_reference(): void {
        $connection = $this->connection();
        $service = new CalendarPublishService();

        $service->publish($connection, $this->gateway(), [$this->item(1)]);
        $gateway = $this->gateway();
        $result = $service->publish($connection, $gateway, [$this->item(1, cancelled: true)]);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(['event-1.ics'], $gateway->deletes);
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_cancelled_item_without_reference_is_noop(): void {
        $connection = $this->connection();
        $gateway = $this->gateway();

        $result = (new CalendarPublishService())->publish($connection, $gateway, [$this->item(1, cancelled: true)]);

        $this->assertSame(['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0], $result);
        $this->assertSame([], $gateway->deletes);
    }

    public function test_gateway_failure_counts_failed_and_keeps_no_reference(): void {
        $connection = $this->connection();
        $gateway = $this->gateway();
        $gateway->putOk = false;

        $result = (new CalendarPublishService())->publish($connection, $gateway, [$this->item(1)]);

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['published']);
        $this->assertSame(0, ExternalReference::query()->count()); // Wiederanlauf beim nächsten Lauf
    }
}
