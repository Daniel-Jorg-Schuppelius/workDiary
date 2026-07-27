<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationBackfillService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\{Organization, StockValuation, StockValuationLayer};
use Illuminate\Support\Carbon;

/**
 * Umstellungshilfe auf schichtbasierte Bewertung (Feature 048, E3). Erzeugt für
 * jede Variante/Lagerort mit Bestand eine initiale FIFO/FEFO-Zugangsschicht aus
 * dem gleitenden Durchschnitt (Menge + Durchschnittskosten), sofern noch keine
 * Schicht existiert. Idempotent – ein erneuter Lauf legt nichts doppelt an.
 */
class ValuationBackfillService {
    public const SCALE = 4;

    public function backfill(Organization $organization): int {
        $created = 0;

        $valuations = StockValuation::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('qty_on_hand', '>', 0)
            ->get();

        foreach ($valuations as $valuation) {
            $hasLayer = StockValuationLayer::query()->withoutGlobalScopes()
                ->where('article_variant_id', $valuation->article_variant_id)
                ->where('warehouse_id', $valuation->warehouse_id)
                ->exists();
            if ($hasLayer) {
                continue;
            }

            StockValuationLayer::query()->create([
                'organization_id' => $organization->id,
                'article_variant_id' => $valuation->article_variant_id,
                'warehouse_id' => $valuation->warehouse_id,
                'qty_remaining' => bcadd($valuation->qty_on_hand, '0', self::SCALE),
                'unit_cost' => bcadd($valuation->avg_cost?->getAmount() ?? '0', '0', self::SCALE),
                'currency' => $valuation->currency,
                'acquired_at' => Carbon::now(),
            ]);
            $created++;
        }

        return $created;
    }
}
