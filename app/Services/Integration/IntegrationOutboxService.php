<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationOutboxService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Integration\IntegrationOutboxStatus;
use App\Jobs\Integration\IntegrationOutboxDeliveryJob;
use App\Models\IntegrationOutboxEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persistierte, generische Integrations-Outbox (Feature 055, MVP-114).
 * Generalisiert das Muster der {@see \App\Services\Inventory\InventoryOutboxService}:
 * höchstens ein Zustellauftrag je Idempotenzschlüssel, Statusübergänge bis zur
 * Bestätigung bzw. Kompensationspflicht (fachlicher Ausgleich, kein Rollback).
 */
class IntegrationOutboxService {
    /**
     * Reiht eine Operation idempotent ein. Existiert bereits ein Eintrag mit
     * demselben Schlüssel, wird dieser zurückgegeben (keine Doppelzustellung).
     *
     * @param array<string, mixed> $payload
     */
    public function enqueue(int $organizationId, string $pluginId, string $operation, array $payload, string $idempotencyKey, ?Model $subject = null): IntegrationOutboxEntry {
        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId, 'idempotency_key' => $idempotencyKey],
            [
                'plugin_id' => $pluginId,
                'operation' => $operation,
                'payload' => $payload,
                'status' => IntegrationOutboxStatus::Pending->value,
                'attempts' => 0,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ],
        );

        if ($entry->wasRecentlyCreated) {
            IntegrationOutboxDeliveryJob::dispatch($entry->id);
        }

        return $entry;
    }

    public function markProcessing(IntegrationOutboxEntry $entry): void {
        $entry->forceFill([
            'status' => IntegrationOutboxStatus::Processing,
            'attempts' => $entry->attempts + 1,
        ])->save();
    }

    public function markConfirmed(IntegrationOutboxEntry $entry): void {
        $entry->forceFill([
            'status' => IntegrationOutboxStatus::Confirmed,
            'last_error' => null,
            'confirmed_at' => Carbon::now(),
        ])->save();
    }

    public function markFailed(IntegrationOutboxEntry $entry, string $error): void {
        $entry->forceFill([
            'status' => IntegrationOutboxStatus::Failed,
            'last_error' => mb_substr($error, 0, 190),
        ])->save();
    }

    public function markCompensationRequired(IntegrationOutboxEntry $entry, string $error): void {
        $entry->forceFill([
            'status' => IntegrationOutboxStatus::CompensationRequired,
            'last_error' => mb_substr($error, 0, 190),
        ])->save();
    }
}
