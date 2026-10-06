<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainEventStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Domain\DomainEventStatus;
use App\Models\Domain\{DomainEvent, DomainProviderConnection};
use App\Services\Domain\DomainEventPollingService;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 7): das Provider-Ereignis führt
 * seinen Stand als Enum. Ein Vergleich gegen die frühere Zeichenkette ist
 * danach still falsch — jedes bereits quittierte Ereignis würde bei jedem
 * Abruf erneut beim Anbieter gelöscht.
 */
final class DomainEventStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const ROW = ['eventid' => 'E-1', 'class' => 'DOMAIN', 'action' => 'EXPIRE', 'object' => 'x.de'];

    private DomainProviderConnection $connection;

    private int $deletes = 0;

    private bool $acknowledge = true;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        $this->connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        FakeDomainResellingTransport::fake([
            'QueryEventList' => FakeDomainResellingTransport::properties([self::ROW]),
            'DeleteEvent' => function (): string {
                $this->deletes++;

                // Ohne EOF gilt die Quittung als gescheitert.
                return "code=200\ndescription=ok\n" . ($this->acknowledge ? "EOF\n" : '');
            },
        ]);
    }

    private function event(): DomainEvent {
        return DomainEvent::query()->withoutGlobalScopes()->sole();
    }

    public function test_an_acknowledged_event_is_not_acknowledged_again(): void {
        $service = app(DomainEventPollingService::class);

        $this->assertSame(['stored' => 1, 'acknowledged' => 1], $service->poll($this->connection));
        $event = $this->event();
        $this->assertSame(DomainEventStatus::Acknowledged, $event->status);
        $this->assertTrue($event->isAcknowledged());
        $this->assertSame('2026-10-05 12:00:00', $event->acknowledged_at?->toDateTimeString());

        // Der Anbieter liefert dasselbe Ereignis noch einmal.
        $this->travelTo('2026-10-05 12:15:00');
        $this->assertSame(['stored' => 0, 'acknowledged' => 0], $service->poll($this->connection));
        $this->assertSame(1, $this->deletes);
        $this->assertSame('2026-10-05 12:00:00', $this->event()->acknowledged_at?->toDateTimeString());
    }

    public function test_a_failed_acknowledge_keeps_the_event_stored_until_the_next_poll(): void {
        $service = app(DomainEventPollingService::class);
        $this->acknowledge = false;

        $this->assertSame(['stored' => 1, 'acknowledged' => 0], $service->poll($this->connection));
        $event = $this->event();
        $this->assertSame(DomainEventStatus::Stored, $event->status);
        $this->assertFalse($event->isAcknowledged());
        $this->assertNull($event->acknowledged_at);
        $this->assertDatabaseHas('domain_events', ['external_event_id' => 'E-1', 'status' => 'stored']);

        $this->acknowledge = true;
        $this->assertSame(['stored' => 0, 'acknowledged' => 1], $service->poll($this->connection));
        $this->assertSame(2, $this->deletes);
        $this->assertDatabaseHas('domain_events', ['external_event_id' => 'E-1', 'status' => 'acknowledged']);
    }

    /** Der Rohhash deckt nur die Anbieterzeile; der Stand geht nicht ein. */
    public function test_the_raw_hash_does_not_depend_on_the_status(): void {
        $service = app(DomainEventPollingService::class);
        $this->acknowledge = false;
        $service->poll($this->connection);
        $stored = $this->event()->raw_hash;

        $this->acknowledge = true;
        $service->poll($this->connection);

        $this->assertSame($stored, $this->event()->raw_hash);
        $this->assertSame(CryptoHelper::hash(JsonHelper::encode(self::ROW)), $stored);
    }

    public function test_status_values_match_the_schema(): void {
        $this->assertSame(['stored', 'acknowledged', 'failed'], array_column(DomainEventStatus::cases(), 'value'));
    }
}
