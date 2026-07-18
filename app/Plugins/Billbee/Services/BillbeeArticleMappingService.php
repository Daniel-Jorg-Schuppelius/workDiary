<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeArticleMappingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Services;

use App\Models\{ArticleVariant, ExternalArticleMapping, Organization};
use App\Plugins\Billbee\Api\BillbeeClientFactory;
use App\Plugins\Billbee\BillbeePlugin;

/**
 * SKU-Mapping-Import (MVP-434): spiegelt Billbee-Produkte in
 * `external_article_mappings`. Zuordnung NUR über exakte SKU-Gleichheit mit
 * der lokalen Varianten-SKU — kein Fuzzy, kein Blind-Create; Produkte ohne
 * lokale Entsprechung bleiben als `pending`-Zeile sichtbar (Pflege im
 * Artikelstamm, dann nächster Lauf).
 */
class BillbeeArticleMappingService {
    public function __construct(private readonly BillbeeClientFactory $clients) {}

    /** @return array{matched: int, pending: int} */
    public function import(Organization $organization): array {
        $client = $this->clients->for((int) $organization->id);
        $pageSize = max(1, min(250, (int) config('plugins.billbee.page_size', 100)));
        $counters = ['matched' => 0, 'pending' => 0];

        for ($page = 1; $page <= 1000; $page++) {
            $result = $client->products($page, $pageSize);
            foreach ($result['data'] as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $this->ingest($organization, $product, $counters);
            }
            if ($page >= $result['total_pages'] || $result['data'] === []) {
                break;
            }
        }

        return $counters;
    }

    /**
     * @param array<string, mixed> $product
     * @param array{matched: int, pending: int} $counters
     */
    private function ingest(Organization $organization, array $product, array &$counters): void {
        $billbeeId = (string) ($product['Id'] ?? '');
        $sku = trim((string) ($product['SKU'] ?? $product['Sku'] ?? ''));
        if ($billbeeId === '') {
            return;
        }

        $variant = $sku !== ''
            ? ArticleVariant::query()->withoutGlobalScopes()
                ->where('sku', $sku)
                ->whereHas('article', fn($q) => $q->withoutGlobalScopes()->where('organization_id', $organization->id))
                ->first()
            : null;

        ExternalArticleMapping::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => BillbeePlugin::ID,
                'external_id' => $billbeeId,
            ],
            [
                'external_number' => $sku !== '' ? $sku : null,
                'article_variant_id' => $variant?->id,
                'article_id' => $variant?->article_id,
                'sync_status' => $variant !== null ? 'synced' : 'pending',
                'last_synced_at' => now(),
            ],
        );

        $variant !== null ? $counters['matched']++ : $counters['pending']++;
    }
}
