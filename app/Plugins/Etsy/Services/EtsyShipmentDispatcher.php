<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyShipmentDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Models\{EtsyConnection, EtsyReceipt, IntegrationOutboxEntry};
use App\Plugins\Etsy\Api\{EtsyApiException, EtsyClientFactory};
use App\Plugins\Etsy\EtsyPlugin;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Versand-Rückkanal über die Integrations-Outbox (Feature 101, MVP-497):
 * `receipt_shipped` meldet Tracking + Carrier an Etsy
 * (`POST .../receipts/{id}/tracking`; ohne Tracking markiert Etsy nur
 * „versendet"). Idempotent gegenüber dem `idempotency_key` UND dem
 * Spiegel-Zustand: bereits versendete/gemeldete Receipts sind ein
 * No-op-Erfolg. Unbekannte Carrier heilen sich selbst über den von Etsy
 * dokumentierten Fallback `other` (W0 §5). Jeder erfolgreiche Submit löst
 * bei Etsy eine Käufer-Benachrichtigung aus — deshalb nie blind wiederholen.
 */
class EtsyShipmentDispatcher implements IntegrationOutboxDispatcher {
    public function __construct(private readonly EtsyClientFactory $clients) {}

    public function pluginId(): string {
        return EtsyPlugin::ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        if ((string) $entry->operation !== 'receipt_shipped') {
            throw new RuntimeException('Unbekannte Etsy-Operation: ' . (string) $entry->operation);
        }

        $receipt = $entry->subject()->withoutGlobalScopes()->first();
        if (! $receipt instanceof EtsyReceipt || (int) $receipt->organization_id !== (int) $entry->organization_id) {
            throw new RuntimeException('receipt_shipped ohne (org-eigene) Spiegelzeile.');
        }

        // Duplikatschutz: bereits gemeldet/versendet → bestätigter No-op.
        if ($receipt->shipped_pushed_at !== null || $receipt->was_shipped) {
            return true;
        }

        $connection = EtsyConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->first();
        if (! $connection instanceof EtsyConnection || ! $connection->isActive()) {
            throw new RuntimeException('Etsy-Verbindung nicht aktiv.');
        }

        $payload = (array) $entry->payload;
        $tracking = trim((string) ($payload['tracking_code'] ?? ''));
        $carrier = trim((string) ($payload['carrier_name'] ?? ''));
        $note = trim((string) ($payload['note_to_buyer'] ?? ''));

        $client = $this->clients->for($connection);
        try {
            $client->createReceiptShipment(
                (int) $connection->shop_id,
                (int) $receipt->receipt_id,
                $tracking !== '' ? $tracking : null,
                $carrier !== '' ? $carrier : null,
                $note !== '' ? $note : null,
            );
        } catch (EtsyApiException $e) {
            // Etsy kennt den Carrier nicht → dokumentierter Fallback `other`
            // (Tracking-Code bleibt erhalten). Nur bei 400 und echtem Carrier.
            if ($e->status === 400 && $carrier !== '' && $carrier !== 'other' && $tracking !== '') {
                $client->createReceiptShipment((int) $connection->shop_id, (int) $receipt->receipt_id, $tracking, 'other', $note !== '' ? $note : null);
            } else {
                throw $e;
            }
        }

        $receipt->forceFill([
            'was_shipped' => true,
            'shipped_pushed_at' => CarbonImmutable::now(),
        ])->save();

        return true;
    }
}
