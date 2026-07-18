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

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Contracts\PluginDispatcher;
use App\Jobs\AbstractOutboxDeliveryJob;
use App\Models\{InventoryOutboxEntry, Organization, PendingExternalConflict, StockMovement};
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryOutboxService};
use App\Services\Licensing\ModuleStatusResolver;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Stellt einen Outbox-Eintrag an das externe Bestandssystem zu (Feature 048,
 * MVP-072). Ablauf im gemeinsamen Skelett (C14); hier nur Modul-Gate und die
 * Kompensation als {@see PendingExternalConflict} (lokale Bewegung bleibt).
 *
 * @extends AbstractOutboxDeliveryJob<InventoryOutboxEntry, ExternalInventoryDispatcher>
 */
class InventoryOutboxDeliveryJob extends AbstractOutboxDeliveryJob {
    public function handle(InventoryOutboxService $outbox, ExternalInventoryDispatcherResolver $resolver): void {
        $this->deliver($outbox, $resolver);
    }

    protected function newEntryQuery(): Builder {
        return InventoryOutboxEntry::query();
    }

    protected function outboxService(): InventoryOutboxService {
        return app(InventoryOutboxService::class);
    }

    /** @param InventoryOutboxEntry $entry */
    protected function shouldDeliver(Model $entry): bool {
        // MVP-052 §5: Modulstatus VOR der Wirkung prüfen — ist „Lager" für die Org
        // deaktiviert, ohne Zustellung beenden (Eintrag bleibt offen, später erneut).
        $org = Organization::query()->withoutGlobalScopes()->find($entry->organization_id);

        return $org === null || app(ModuleStatusResolver::class)->isActiveFor($org, 'module.lager');
    }

    /**
     * @param ExternalInventoryDispatcher $dispatcher
     * @param InventoryOutboxEntry $entry
     */
    protected function dispatchEntry(PluginDispatcher $dispatcher, Model $entry): bool {
        return $dispatcher->dispatch($entry);
    }

    /** @param InventoryOutboxEntry $entry */
    protected function compensateEntry(Model $entry, string $reason): void {
        if ($entry->stock_movement_id === null) {
            return;
        }

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
