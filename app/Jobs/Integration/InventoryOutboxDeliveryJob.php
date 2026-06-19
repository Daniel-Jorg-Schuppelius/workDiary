<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryOutboxDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Integration;

use App\Models\{InventoryOutboxEntry, PendingExternalConflict, StockMovement};
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryOutboxService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use RuntimeException;
use Throwable;

/**
 * Stellt einen Outbox-Eintrag an das externe Bestandssystem zu (Feature 048,
 * MVP-072). Idempotent über den `idempotency_key`; Wiederholung mit Backoff über
 * die Queue. Nach Aufbrauchen aller Versuche wird der Eintrag als
 * kompensationspflichtig markiert und – falls eine lokale Bewegung existiert –
 * ein {@see PendingExternalConflict} zur manuellen Auflösung angelegt (die lokal
 * gebuchte Bewegung bleibt bestehen; Ausgleich erfolgt fachlich).
 */
class InventoryOutboxDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $entryId) {}

    public function handle(InventoryOutboxService $outbox, ExternalInventoryDispatcherResolver $resolver): void {
        $entry = InventoryOutboxEntry::query()->withoutGlobalScopes()->find($this->entryId);
        if ($entry === null || $entry->status->isTerminal()) {
            return;
        }

        $dispatcher = $resolver->for($entry->plugin_id);
        if ($dispatcher === null) {
            // Kein Plugin registriert → kann nicht zugestellt werden. Nicht endlos
            // wiederholen; die Bereitstellung erfolgt mit dem Plugin (MVP-073).
            $outbox->markFailed($entry, 'kein Dispatcher für Plugin: ' . ($entry->plugin_id ?? '—'));

            return;
        }

        $outbox->markProcessing($entry);

        try {
            if ($dispatcher->dispatch($entry)) {
                $outbox->markConfirmed($entry);

                return;
            }

            throw new RuntimeException('extern nicht bestätigt');
        } catch (Throwable $e) {
            if ($this->attempts() < $this->tries) {
                $outbox->markFailed($entry, $e->getMessage());

                throw $e; // Queue-Wiederholung auslösen
            }

            $this->compensate($entry, $outbox, $e->getMessage());
        }
    }

    /** Sicherheitsnetz der Queue nach Aufbrauchen aller Versuche. */
    public function failed(?Throwable $e): void {
        $entry = InventoryOutboxEntry::query()->withoutGlobalScopes()->find($this->entryId);
        if ($entry === null || $entry->status->isTerminal()) {
            return;
        }

        $this->compensate($entry, app(InventoryOutboxService::class), $e?->getMessage() ?? 'Zustellung fehlgeschlagen');
    }

    private function compensate(InventoryOutboxEntry $entry, InventoryOutboxService $outbox, string $reason): void {
        $outbox->markCompensationRequired($entry, $reason);

        if ($entry->stock_movement_id !== null) {
            PendingExternalConflict::query()->withoutGlobalScopes()->create([
                'organization_id' => $entry->organization_id,
                'plugin_id' => $entry->plugin_id ?? 'inventory',
                'conflict_type' => 'inventory_outbox',
                'referenceable_type' => (new StockMovement())->getMorphClass(),
                'referenceable_id' => $entry->stock_movement_id,
                'external_id' => null,
                'local_snapshot' => $entry->payload,
                'remote_snapshot' => [],
                'diff_fields' => null,
                'status' => PendingExternalConflict::STATUS_OPEN,
            ]);
        }
    }
}
