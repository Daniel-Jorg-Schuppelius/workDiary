<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlArticleImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\{Article, ArticleVariant, ExternalArticleMapping, IntegrationInboxItem, JtlConnection};
use App\Plugins\JtlWawi\Api\{JtlApiException, JtlGatewayFactory};
use App\Plugins\JtlWawi\JtlWawiPlugin;

/**
 * Artikel-/Variantenprojektion aus JTL-Wawi (Feature 078, MVP-318).
 *
 * Vater-/Kind-Abbildung: WorkDiary-Hauptartikel ↔ JTL-Vaterartikel,
 * WorkDiary-Variante ↔ JTL-Kindartikel; Bestände laufen nur gegen den
 * Kindartikel ({@see ExternalArticleMapping} mit `external_parent_id`).
 *
 * Matching (keine Schattenstammdaten — WorkDiary legt NIE automatisch
 * Artikel an): sichere Treffer über exakte SKU, sekundär eindeutige GTIN.
 * Kein Treffer → Integrations-Inbox `unmatched`; mehrdeutige GTIN →
 * `ambiguous`. Idempotent über `dedupe_key`.
 *
 * Die Artikel-Liste (`GET /v2/items` mit `changedSince`) existiert erst ab
 * v2.1 (Pre-Release). Fehlt der Endpunkt (v2.0), wird das als
 * Vertragsabweichung vermerkt und der Artikelsync sichtbar übersprungen —
 * kein stiller Teilerfolg (Abweichungsregister MVP-316; GraphQL-Fallback
 * ist Nach-MVP).
 */
class JtlArticleImporter {
    public const DEVIATION_ITEMS_LIST = 'items_list_unavailable: GET /v2/items fehlt (Wawi < v2.1) — Artikelsync deaktiviert, GraphQL-Fallback Nach-MVP.';

    public function __construct(private readonly JtlGatewayFactory $gateways) {}

    /** @return array{seen: int, linked: int, unmatched: int, ambiguous: int, skipped: bool} */
    public function import(JtlConnection $connection): array {
        $gateway = $this->gateways->for($connection);
        $pageSize = (int) config('plugins.' . JtlWawiPlugin::ID . '.page_size', 100);
        $budget = (int) config('plugins.' . JtlWawiPlugin::ID . '.sync_page_budget', 20);
        $runStartedAt = now();

        $counters = ['seen' => 0, 'linked' => 0, 'unmatched' => 0, 'ambiguous' => 0, 'skipped' => false];
        $query = [];
        if ($connection->article_checkpoint_at !== null) {
            $query['changedSince'] = $connection->article_checkpoint_at->toIso8601String();
        }

        $page = 1;
        do {
            try {
                $envelope = $gateway->items($query, $page, $pageSize);
            } catch (JtlApiException $e) {
                if ($e->isMissingEndpoint()) {
                    $connection->noteContractDeviation(self::DEVIATION_ITEMS_LIST);
                    $counters['skipped'] = true;

                    return $counters;
                }
                throw $e;
            }

            foreach ((array) ($envelope['items'] ?? []) as $row) {
                $counters['seen']++;
                $outcome = $this->projectItem($connection, (array) $row);
                if ($outcome !== null) {
                    $counters[$outcome]++;
                }
            }

            $hasNext = (bool) ($envelope['hasNextPage'] ?? false);
            $page++;
        } while ($hasNext && $page <= $budget);

        $connection->forceFill(['article_checkpoint_at' => $runStartedAt])->save();

        return $counters;
    }

    /**
     * Projiziert einen einzelnen JTL-Artikel; liefert den Zähler-Schlüssel
     * des Ergebnisses (linked/unmatched/ambiguous) oder null (Vater/leer).
     *
     * @param  array<string, mixed>  $row
     * @return 'linked'|'unmatched'|'ambiguous'|null
     */
    private function projectItem(JtlConnection $connection, array $row): ?string {
        $jtlItemId = trim((string) ($row['id'] ?? ''));
        $sku = trim((string) ($row['sKU'] ?? ($row['sku'] ?? '')));
        if ($jtlItemId === '') {
            return null;
        }

        $isParent = ((array) ($row['childItems'] ?? [])) !== [];
        if ($isParent) {
            $this->projectParent($connection, $jtlItemId, $sku);

            return null;
        }

        $match = app(\App\Services\Inventory\VariantMatcher::class)->match(
            (int) $connection->organization_id,
            $sku,
            trim((string) data_get($row, 'identifiers.gtin', '')),
        );
        if ($match['ambiguous']) {
            $this->inbox($connection, $jtlItemId, $row, IntegrationInboxItem::CASE_AMBIGUOUS);

            return 'ambiguous';
        }
        $variant = $match['variant'];
        if (! $variant instanceof ArticleVariant) {
            $this->inbox($connection, $jtlItemId, $row, IntegrationInboxItem::CASE_UNMATCHED);

            return 'unmatched';
        }

        ExternalArticleMapping::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'plugin_id' => JtlWawiPlugin::ID,
                'external_id' => $jtlItemId,
            ],
            [
                'article_id' => $variant->article_id,
                'article_variant_id' => $variant->id,
                'external_parent_id' => trim((string) ($row['parentItemId'] ?? '')) ?: null,
                'external_number' => $sku !== '' ? mb_substr($sku, 0, 64) : null,
                'sync_status' => 'linked',
                'last_synced_at' => now(),
            ],
        );
        // Offene Inbox-Fälle zu diesem Artikel sind damit erledigt.
        IntegrationInboxItem::query()
            ->where('organization_id', $connection->organization_id)
            ->where('dedupe_key', $this->dedupeKey($jtlItemId))
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->update(['status' => IntegrationInboxItem::STATUS_RESOLVED_LINKED, 'resolved_at' => now()]);

        return 'linked';
    }

    /** Vaterartikel: Zuordnung auf den WorkDiary-Hauptartikel (nur Projektion). */
    private function projectParent(JtlConnection $connection, string $jtlItemId, string $sku): void {
        $article = $sku === '' ? null : Article::query()
            ->where('organization_id', $connection->organization_id)
            ->where('number', $sku)
            ->first();

        ExternalArticleMapping::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'plugin_id' => JtlWawiPlugin::ID,
                'external_id' => $jtlItemId,
            ],
            [
                'article_id' => $article?->id,
                'article_variant_id' => null,
                'external_parent_id' => null,
                'external_number' => $sku !== '' ? mb_substr($sku, 0, 64) : null,
                'sync_status' => $article !== null ? 'linked' : 'pending',
                'last_synced_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private function inbox(JtlConnection $connection, string $jtlItemId, array $row, string $caseType): void {
        IntegrationInboxItem::query()->firstOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'dedupe_key' => $this->dedupeKey($jtlItemId),
            ],
            [
                'plugin_id' => JtlWawiPlugin::ID,
                'source' => JtlWawiPlugin::ID,
                'target_type' => 'article_variant',
                'external_type' => 'item',
                'external_id' => $jtlItemId,
                'case_type' => $caseType,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'display_title' => trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : $jtlItemId,
                'display_subtitle' => trim('SKU ' . (string) ($row['sKU'] ?? '-') . ' · GTIN ' . ((string) data_get($row, 'identifiers.gtin') ?: '-')),
                'remote_snapshot' => [
                    'id' => $jtlItemId,
                    'sku' => (string) ($row['sKU'] ?? ''),
                    'gtin' => (string) data_get($row, 'identifiers.gtin', ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'parentItemId' => (string) ($row['parentItemId'] ?? ''),
                ],
                'occurred_at' => now(),
            ],
        );
    }

    private function dedupeKey(string $jtlItemId): string {
        return JtlWawiPlugin::ID . ':item:' . $jtlItemId;
    }
}
