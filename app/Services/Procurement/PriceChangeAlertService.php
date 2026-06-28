<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceChangeAlertService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Article, PricingChangeAlert, SupplierCatalogItem};

/**
 * Erzeugt Kalkulationswarnungen bei Einkaufspreisänderungen (Feature 050,
 * MVP-094): Steigt der Lieferanten-EK eines verknüpften Artikels so, dass der
 * hinterlegte Verkaufspreis unter die greifende Mindestmarge (oder unter 0)
 * fällt, entsteht eine offene Warnung. Verkaufspreise werden nicht automatisch
 * geändert — die Reaktion bleibt beim Anwender.
 */
class PriceChangeAlertService {
    public function __construct(private readonly PriceSuggestionService $pricing) {}

    /**
     * Bewertet eine Preisänderung eines Katalogartikels und legt bei
     * Unterschreitung der Mindestmarge eine Warnung an.
     */
    public function evaluate(SupplierCatalogItem $item, ?string $oldPrice, string $newPrice): ?PricingChangeAlert {
        if ($item->article_id === null) {
            return null;
        }

        $article = Article::query()->find($item->article_id);
        if (! $article instanceof Article || $article->default_sale_price === null) {
            return null;
        }

        $sale = (float) $article->default_sale_price;
        if ($sale <= 0) {
            return null;
        }

        $newMargin = ($sale - (float) $newPrice) / $sale * 100;
        $rule = $this->pricing->resolveRule($item->organization_id, $item->supplier_id, $item->category);
        $minMargin = $rule !== null && $rule->min_margin !== null ? (float) $rule->min_margin : null;

        $below = ($minMargin !== null && $newMargin < $minMargin - 0.0001) || $newMargin < 0;
        if (! $below) {
            return null;
        }

        return PricingChangeAlert::query()->create([
            'organization_id' => $item->organization_id,
            'supplier_catalog_item_id' => $item->id,
            'article_id' => $article->id,
            'supplier_id' => $item->supplier_id,
            'old_purchase_price' => $oldPrice,
            'new_purchase_price' => $newPrice,
            'sale_price' => $article->default_sale_price,
            'new_margin' => round($newMargin, 3),
            'min_margin' => $minMargin,
            'status' => PricingChangeAlert::STATUS_OPEN,
        ]);
    }
}
