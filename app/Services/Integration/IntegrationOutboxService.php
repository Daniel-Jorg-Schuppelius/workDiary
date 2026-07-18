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

use App\Contracts\OutboxTransitionService;
use App\Enums\Integration\IntegrationOutboxStatus;
use App\Jobs\Integration\IntegrationOutboxDeliveryJob;
use App\Models\IntegrationOutboxEntry;
use App\Services\Concerns\ManagesOutboxTransitions;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistierte, generische Integrations-Outbox (Feature 055, MVP-114).
 * Generalisiert das Muster der {@see \App\Services\Inventory\InventoryOutboxService}:
 * höchstens ein Zustellauftrag je Idempotenzschlüssel, Statusübergänge bis zur
 * Bestätigung bzw. Kompensationspflicht (fachlicher Ausgleich, kein Rollback).
 *
 * @implements OutboxTransitionService<IntegrationOutboxEntry>
 */
class IntegrationOutboxService implements OutboxTransitionService {
    /** @use ManagesOutboxTransitions<IntegrationOutboxEntry> */
    use ManagesOutboxTransitions;

    /**
     * Reiht eine Operation idempotent ein. Existiert bereits ein Eintrag mit
     * demselben Schlüssel, wird dieser zurückgegeben (keine Doppelzustellung).
     *
     * @param array<string, mixed> $payload
     */
    public function enqueue(int $organizationId, string $pluginId, string $operation, array $payload, string $idempotencyKey, ?Model $subject = null): IntegrationOutboxEntry {
        return $this->enqueueOutboxEntry(
            IntegrationOutboxEntry::class,
            IntegrationOutboxDeliveryJob::class,
            $organizationId,
            $idempotencyKey,
            [
                'plugin_id' => $pluginId,
                'operation' => $operation,
                'payload' => $payload,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ],
        );
    }

    protected function outboxStatusEnum(): string {
        return IntegrationOutboxStatus::class;
    }

    /** Spaltenbreite last_error: 190 Zeichen. */
    protected function normalizeOutboxError(string $error): string {
        return mb_substr($error, 0, 190);
    }
}
