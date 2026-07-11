<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlStockChangePoller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\{IntegrationInboxItem, JtlConnection};
use App\Plugins\JtlWawi\Api\JtlGatewayFactory;
use App\Plugins\JtlWawi\JtlWawiPlugin;

/**
 * Bestands-Delta-Polling über das JTL-Änderungsjournal (Feature 078,
 * MVP-320/322): liest `GET /v2/stocks/changes?startDate=<checkpoint>`
 * seitenweise (Laufbudget), erneuert die Snapshots der berührten
 * Artikel×Lager-Paare und legt für unbekannte JTL-Artikel Inbox-Fälle an.
 * Eigene Buchungen (Quellmarker `workdiary:`) werden erkannt und nur als
 * Snapshot-Refresh behandelt. Der Checkpoint ist der Lauf-Start —
 * Änderungen während des Laufs kommen im nächsten Lauf erneut (idempotent).
 */
class JtlStockChangePoller {
    public const MARKER_PREFIX = 'workdiary:';

    private const MAX_REFRESH_PAIRS = 50;

    public function __construct(
        private readonly JtlGatewayFactory $gateways,
        private readonly JtlMappingResolver $mappings,
        private readonly JtlStockReader $reader,
    ) {}

    /** @return array{changes: int, refreshed: int, unknown_items: int, truncated: bool} */
    public function poll(JtlConnection $connection): array {
        $gateway = $this->gateways->for($connection);
        $pageSize = (int) config('plugins.' . JtlWawiPlugin::ID . '.page_size', 100);
        $budget = (int) config('plugins.' . JtlWawiPlugin::ID . '.sync_page_budget', 20);
        $runStartedAt = now();
        $since = $connection->stock_checkpoint_at ?? now()->subDay();

        $counters = ['changes' => 0, 'refreshed' => 0, 'unknown_items' => 0, 'truncated' => false];
        /** @var array<string, array{item: string, warehouse: string}> $touched */
        $touched = [];

        $page = 1;
        do {
            $envelope = $gateway->stockChanges($since, null, $page, $pageSize);

            foreach ((array) ($envelope['items'] ?? []) as $row) {
                $counters['changes']++;
                $itemId = trim((string) ($row['itemId'] ?? ''));
                $warehouseId = trim((string) ($row['warehouseId'] ?? ''));
                if ($itemId === '' || $warehouseId === '') {
                    continue;
                }
                $touched[$itemId . '|' . $warehouseId] = ['item' => $itemId, 'warehouse' => $warehouseId];
            }

            $hasNext = (bool) ($envelope['hasNextPage'] ?? false);
            if ($hasNext && $page >= $budget) {
                // Laufbudget erschöpft: Checkpoint NICHT vorziehen, damit der
                // nächste Lauf nahtlos weiterliest — kein stilles Auslassen.
                $counters['truncated'] = true;
                break;
            }
            $page++;
        } while ($hasNext);

        foreach ($touched as $pair) {
            $variant = $this->mappings->variantForJtlItemId((int) $connection->organization_id, $pair['item']);
            if ($variant === null) {
                $counters['unknown_items']++;
                $this->unknownItemInbox($connection, $pair['item']);

                continue;
            }

            $warehouse = $this->mappings->warehouseForJtlId((int) $connection->organization_id, $pair['warehouse']);
            if ($warehouse === null) {
                continue; // Nicht zugeordnetes Lager: Zuordnung ist Admin-Aufgabe, sichtbar im JTL-Admin.
            }

            if ($counters['refreshed'] >= self::MAX_REFRESH_PAIRS) {
                $counters['truncated'] = true;
                break;
            }

            $this->reader->refresh($variant, $warehouse);
            $counters['refreshed']++;
        }

        if (! $counters['truncated']) {
            $connection->forceFill(['stock_checkpoint_at' => $runStartedAt])->save();
        }

        return $counters;
    }

    private function unknownItemInbox(JtlConnection $connection, string $jtlItemId): void {
        IntegrationInboxItem::query()->firstOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'dedupe_key' => JtlWawiPlugin::ID . ':item:' . $jtlItemId,
            ],
            [
                'plugin_id' => JtlWawiPlugin::ID,
                'source' => JtlWawiPlugin::ID,
                'target_type' => 'article_variant',
                'external_type' => 'item',
                'external_id' => $jtlItemId,
                'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'display_title' => __('Unbekannter JTL-Artikel mit Bestandsänderung'),
                'display_subtitle' => 'JTL-ID ' . $jtlItemId,
                'remote_snapshot' => ['id' => $jtlItemId],
                'occurred_at' => now(),
            ],
        );
    }
}
