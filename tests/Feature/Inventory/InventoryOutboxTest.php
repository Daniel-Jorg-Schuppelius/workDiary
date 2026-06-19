<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryOutboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Enums\Inventory\OutboxStatus;
use App\Jobs\Integration\InventoryOutboxDeliveryJob;
use App\Models\{InventoryOutboxEntry, PendingExternalConflict, StockMovement};
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryOutboxService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Persistierte Bestands-Outbox (Feature 048, MVP-072): Idempotenz beim Einreihen,
 * Bestätigung bei erfolgreicher Zustellung, Wiederholung bei transientem Fehler
 * und Kompensationspflicht + Konflikt nach endgültigem Fehlschlag.
 */
final class InventoryOutboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryOutboxService $outbox;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->outbox = app(InventoryOutboxService::class);
    }

    public function test_enqueue_is_idempotent(): void {
        Bus::fake();

        $a = $this->outbox->enqueue($this->organization->id, 'jtl', 'receipt', ['x' => 1], 'KEY-1');
        $b = $this->outbox->enqueue($this->organization->id, 'jtl', 'receipt', ['x' => 1], 'KEY-1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, InventoryOutboxEntry::query()->count());
        Bus::assertDispatchedTimes(InventoryOutboxDeliveryJob::class, 1);
    }

    public function test_successful_delivery_confirms_entry(): void {
        $this->registerDispatcher(fn () => true);
        $entry = $this->makeEntry('OK-1');

        $this->runJob($entry);

        $this->assertSame(OutboxStatus::Confirmed, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->confirmed_at);
    }

    public function test_transient_failure_marks_failed_and_retries(): void {
        $this->registerDispatcher(function (): bool {
            throw new RuntimeException('API down');
        });
        $entry = $this->makeEntry('ERR-1');

        try {
            $this->runJob($entry);
            $this->fail('Job sollte zur Wiederholung eine Exception werfen.');
        } catch (RuntimeException) {
            // erwartet – Queue würde erneut zustellen
        }

        $this->assertSame(OutboxStatus::Failed, $entry->fresh()->status);
        $this->assertSame('API down', $entry->fresh()->last_error);
    }

    public function test_exhausted_delivery_requires_compensation_and_opens_conflict(): void {
        $movement = StockMovement::factory()->create(['organization_id' => $this->organization->id]);
        $entry = $this->makeEntry('FATAL-1', $movement->id);

        // Sicherheitsnetz der Queue nach Aufbrauchen aller Versuche.
        (new InventoryOutboxDeliveryJob($entry->id))->failed(new RuntimeException('endgültig'));

        $this->assertSame(OutboxStatus::CompensationRequired, $entry->fresh()->status);
        $this->assertDatabaseHas('pending_external_conflicts', [
            'conflict_type' => 'inventory_outbox',
            'referenceable_id' => $movement->id,
            'status' => PendingExternalConflict::STATUS_OPEN,
        ]);
    }

    private function makeEntry(string $key, ?int $movementId = null): InventoryOutboxEntry {
        Bus::fake(); // Auto-Dispatch beim Einreihen unterdrücken – Job wird manuell ausgeführt
        return $this->outbox->enqueue($this->organization->id, 'jtl', 'receipt', ['k' => $key], $key, $movementId);
    }

    private function runJob(InventoryOutboxEntry $entry): void {
        (new InventoryOutboxDeliveryJob($entry->id))->handle(
            $this->outbox,
            app(ExternalInventoryDispatcherResolver::class),
        );
    }

    /** @param callable():bool $dispatch */
    private function registerDispatcher(callable $dispatch): void {
        $dispatcher = new class($dispatch) implements ExternalInventoryDispatcher {
            /** @param callable():bool $dispatch */
            public function __construct(private $dispatch) {}

            public function pluginId(): string {
                return 'jtl';
            }

            public function dispatch(InventoryOutboxEntry $entry): bool {
                return ($this->dispatch)();
            }
        };
        app(ExternalInventoryDispatcherResolver::class)->register($dispatcher);
    }
}
