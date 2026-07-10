<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryOutboxService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\OutboxStatus;
use App\Jobs\Integration\InventoryOutboxDeliveryJob;
use App\Models\{InventoryOutboxEntry, StockMovement};
use Illuminate\Support\Carbon;

/**
 * Persistierte Outbox für die externe Bestandsführung (Feature 048, MVP-072).
 * Stellt sicher, dass jede lokal gebuchte Bewegung höchstens einen externen
 * Zustellauftrag erzeugt (Idempotenz über `idempotency_key`) und reicht
 * Statusübergänge bis zur Bestätigung bzw. Kompensationspflicht durch.
 */
class InventoryOutboxService {
    /**
     * Reiht eine Operation idempotent ein. Existiert bereits ein Eintrag mit
     * demselben Schlüssel, wird dieser zurückgegeben (keine Doppelzustellung).
     *
     * @param array<string, mixed> $payload
     */
    public function enqueue(int $organizationId, ?string $pluginId, string $operation, array $payload, string $idempotencyKey, ?int $stockMovementId = null): InventoryOutboxEntry {
        $entry = InventoryOutboxEntry::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId, 'idempotency_key' => $idempotencyKey],
            [
                'plugin_id' => $pluginId,
                'operation' => $operation,
                'payload' => $payload,
                'status' => OutboxStatus::Pending->value,
                'attempts' => 0,
                'stock_movement_id' => $stockMovementId,
            ],
        );

        if ($entry->wasRecentlyCreated) {
            // afterCommit: s. IntegrationOutboxService — Enqueue passiert in
            // Business-Transaktionen, der Job erst nach dem Commit.
            InventoryOutboxDeliveryJob::dispatch($entry->id)->afterCommit();
        }

        return $entry;
    }

    /** Reiht eine bereits gebuchte Bewegung zur externen Spiegelung ein. */
    public function enqueueForMovement(StockMovement $movement, ?string $pluginId): InventoryOutboxEntry {
        $key = $movement->idempotency_key ?? ('movement:' . $movement->id);

        return $this->enqueue(
            (int) $movement->organization_id,
            $pluginId,
            (string) $movement->movement_type->value,
            [
                'article_variant_id' => $movement->article_variant_id,
                'warehouse_id' => $movement->warehouse_id,
                'stock_state' => $movement->stock_state->value,
                'movement_type' => $movement->movement_type->value,
                'qty_base' => $movement->qty_base,
                'occurred_at' => $movement->occurred_at->toIso8601String(),
            ],
            $key,
            $movement->id,
        );
    }

    /**
     * Inbound-Bestätigung (Webhook-Rückkanal): markiert den Eintrag mit dem
     * Schlüssel als bestätigt. Idempotent; liefert false, wenn unbekannt.
     */
    public function confirmByKey(int $organizationId, string $idempotencyKey): bool {
        $entry = InventoryOutboxEntry::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($entry === null) {
            return false;
        }

        if ($entry->status !== OutboxStatus::Confirmed) {
            $this->markConfirmed($entry);
        }

        return true;
    }

    public function markProcessing(InventoryOutboxEntry $entry): void {
        $entry->forceFill([
            'status' => OutboxStatus::Processing,
            'attempts' => $entry->attempts + 1,
        ])->save();
    }

    public function markConfirmed(InventoryOutboxEntry $entry): void {
        $entry->forceFill([
            'status' => OutboxStatus::Confirmed,
            'last_error' => null,
            'confirmed_at' => Carbon::now(),
        ])->save();
    }

    public function markFailed(InventoryOutboxEntry $entry, string $error): void {
        $entry->forceFill([
            'status' => OutboxStatus::Failed,
            'last_error' => $error,
        ])->save();
    }

    public function markCompensationRequired(InventoryOutboxEntry $entry, string $error): void {
        $entry->forceFill([
            'status' => OutboxStatus::CompensationRequired,
            'last_error' => $error,
        ])->save();
    }
}
