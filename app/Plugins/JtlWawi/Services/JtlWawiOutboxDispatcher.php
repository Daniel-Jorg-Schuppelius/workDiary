<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\{ArticleVariant, InventoryOutboxEntry, StockSerial, Warehouse};
use App\Plugins\JtlWawi\Api\{JtlGateway, JtlGatewayFactory};
use App\Plugins\JtlWawi\JtlWawiPlugin;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Zustellung der `inventory_outbox` an JTL-Wawi (Feature 078, MVP-321) —
 * löst den Scaffold aus MVP-073 ab.
 *
 * Die JTL-API kennt KEINEN Idempotenz-Key für `POST /v2/stocks`. Idempotenz
 * entsteht deshalb über den Quellmarker im `comment`-Feld
 * (`workdiary:<idempotency_key>`) plus Vorprüfung im Änderungsjournal:
 * vor JEDEM Buchungsversuch (auch Queue-Retries nach Timeout) wird
 * `GET /v2/stocks/changes` im Rückschau-Fenster nach dem Marker durchsucht —
 * Treffer ⇒ bereits verbucht ⇒ bestätigen ohne zweite Buchung. Bleibt der
 * Ausgang nach allen Versuchen unklar, übernimmt der
 * {@see \App\Jobs\Integration\InventoryOutboxDeliveryJob} die Eskalation zu
 * `compensation_required` + Konflikt — nie blinde Wiederholung ohne
 * Vorprüfung, nie stiller Verlust.
 */
class JtlWawiOutboxDispatcher implements ExternalInventoryDispatcher {
    public const MARKER_PREFIX = 'workdiary:';

    private const RECONCILE_MAX_PAGES = 10;

    public function __construct(
        private readonly JtlGatewayFactory $gateways,
        private readonly JtlMappingResolver $mappings,
    ) {}

    public function pluginId(): string {
        return JtlWawiPlugin::ID;
    }

    public function dispatch(InventoryOutboxEntry $entry): bool {
        $payload = (array) $entry->payload;

        if (! $this->isExternallyRelevant($payload)) {
            return true; // Reservierungen/nicht-physische Zustände verwaltet JTL selbst.
        }

        $connection = $this->mappings->activeConnectionFor((int) $entry->organization_id);

        $variant = ArticleVariant::withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->find($payload['article_variant_id'] ?? 0);
        $warehouse = Warehouse::withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->find($payload['warehouse_id'] ?? 0);
        if (! $variant instanceof ArticleVariant || ! $warehouse instanceof Warehouse) {
            throw new RuntimeException('JTL-Wawi: Variante oder Lager der Outbox-Buchung existiert nicht mehr.');
        }

        $itemId = $this->mappings->requireExternalItemIdFor($variant);
        $jtlWarehouseIds = $this->mappings->requireJtlWarehouseIdsFor($warehouse);
        if (count($jtlWarehouseIds) !== 1) {
            throw new RuntimeException(sprintf(
                'JTL-Wawi: Lager „%s“ ist %d JTL-Lagern zugeordnet — für den Schreibpfad ist eine eindeutige 1:1-Zuordnung nötig.',
                (string) $warehouse->name,
                count($jtlWarehouseIds),
            ));
        }

        $marker = self::MARKER_PREFIX . $entry->idempotency_key;
        $occurredAt = Carbon::parse((string) ($payload['occurred_at'] ?? now()->toIso8601String()));
        $gateway = $this->gateways->for($connection);

        // Idempotenz-Vorprüfung: wurde dieses Delta (z. B. nach Timeout im
        // vorigen Versuch) bereits verbucht?
        if ($this->alreadyApplied($gateway, $itemId, $marker, $occurredAt)) {
            return true;
        }

        $gateway->postStockAdjustment($this->buildAdjustment($payload, $itemId, $jtlWarehouseIds[0], $marker));

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function isExternallyRelevant(array $payload): bool {
        $state = (string) ($payload['stock_state'] ?? StockState::Physical->value);
        $type = (string) ($payload['movement_type'] ?? '');
        $qty = (float) ($payload['qty_base'] ?? 0);

        return $state === StockState::Physical->value
            && $qty !== 0.0
            && ! in_array($type, [StockMovementType::Reserve->value, StockMovementType::ReleaseReservation->value], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildAdjustment(array $payload, string $itemId, string $jtlWarehouseId, string $marker): array {
        $qty = (float) ($payload['qty_base'] ?? 0);

        $adjustment = [
            'warehouseId' => $jtlWarehouseId,
            'itemId' => $itemId,
            'quantity' => $qty,
            'comment' => $marker,
        ];

        // Seriennummern nur beim positiven Einzelstück-Zugang mitgeben —
        // die API verlangt count(serialNumbers) == quantity; das Verhalten
        // bei negativen Deltas ist nicht dokumentiert (Abweichungsregister).
        $serialId = $payload['stock_serial_id'] ?? null;
        if ($serialId !== null && $qty === 1.0) {
            $serial = StockSerial::withoutGlobalScopes()->find($serialId);
            if ($serial instanceof StockSerial && trim((string) $serial->serial_no) !== '') {
                $adjustment['serialNumbers'] = [(string) $serial->serial_no];
            }
        }

        return $adjustment;
    }

    /** Durchsucht das Änderungsjournal im Rückschau-Fenster nach dem Quellmarker. */
    private function alreadyApplied(JtlGateway $gateway, string $itemId, string $marker, Carbon $occurredAt): bool {
        $lookback = (int) config('plugins.' . JtlWawiPlugin::ID . '.reconcile_lookback_minutes', 10);
        $since = $occurredAt->copy()->subMinutes($lookback);

        $page = 1;
        do {
            $envelope = $gateway->stockChanges($since, $itemId, $page);

            foreach ((array) ($envelope['items'] ?? []) as $row) {
                if ((string) ($row['comment'] ?? '') === $marker) {
                    return true;
                }
            }

            $hasNext = (bool) ($envelope['hasNextPage'] ?? false);
            $page++;
        } while ($hasNext && $page <= self::RECONCILE_MAX_PAGES);

        return false;
    }
}
