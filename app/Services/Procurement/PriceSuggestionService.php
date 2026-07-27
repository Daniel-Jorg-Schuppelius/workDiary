<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Article, PricingMarginRule, SupplierCatalogItem};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;

/**
 * Erzeugt Verkaufspreisvorschläge aus Margenregeln (Feature 050, MVP-095).
 * Die Übernahme in den Artikelstamm erfolgt ausschließlich nach ausdrücklicher
 * Freigabe ({@see applyToArticle()}); historische Vorgänge bleiben unberührt.
 */
class PriceSuggestionService {
    /**
     * Wählt die spezifischste aktive Regel (Lieferant+Warengruppe > eines >
     * global), Gleichstand nach Priorität.
     */
    public function resolveRule(int $organizationId, ?int $supplierId, ?string $category): ?PricingMarginRule {
        $candidates = PricingMarginRule::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('supplier_id')->orWhere('supplier_id', $supplierId))
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', $category))
            ->get();

        // Spezifischste zuerst (Lieferant+Warengruppe > eines > global),
        // Gleichstand nach Priorität, dann jüngste Regel.
        return $candidates->sortByDesc(fn (PricingMarginRule $r): array => [
            ($r->supplier_id !== null ? 2 : 0) + ($r->category !== null ? 1 : 0),
            $r->priority,
            $r->id,
        ])->first();
    }

    /**
     * Berechnet den Vorschlag aus einer Regel und einem Einkaufspreis.
     *
     * @return array{price: string, margin: float, below_min: bool}|null
     */
    public function suggest(PricingMarginRule $rule, string $purchasePrice): ?array {
        $p = (float) $purchasePrice;
        if ($p <= 0) {
            return null;
        }

        $raw = null;
        if ($rule->target_margin !== null) {
            $m = (float) $rule->target_margin->getNumericValue() / 100;
            if ($m > 0 && $m < 1) {
                $raw = $p / (1 - $m);
            }
        }
        if ($raw === null && $rule->markup_percent !== null) {
            $raw = $p * (1 + (float) $rule->markup_percent->getNumericValue() / 100);
        }
        if ($raw === null) {
            return null;
        }

        $price = $rule->rounding->apply($raw);
        if ($rule->min_sale_price !== null) {
            $price = max($price, $rule->min_sale_price->toFloat());
        }
        $price = round($price, 2);

        $margin = $price > 0 ? ($price - $p) / $price * 100 : 0.0;
        $belowMin = $rule->min_margin !== null && $margin < (float) $rule->min_margin->getNumericValue() - 0.0001;

        return [
            'price' => number_format($price, 2, '.', ''),
            'margin' => round($margin, 1),
            'below_min' => $belowMin,
        ];
    }

    /**
     * Vorschlag für einen Katalogartikel (Regel auflösen + berechnen).
     *
     * @return array{rule: PricingMarginRule, price: string, margin: float, below_min: bool}|null
     */
    public function suggestForItem(SupplierCatalogItem $item): ?array {
        if ($item->purchase_price === null) {
            return null;
        }

        $rule = $this->resolveRule($item->organization_id, $item->supplier_id, $item->category);
        if ($rule === null) {
            return null;
        }

        $suggestion = $this->suggest($rule, $item->purchase_price->getAmount());

        return $suggestion === null ? null : ['rule' => $rule] + $suggestion;
    }

    /**
     * Übernimmt den vorgeschlagenen Verkaufspreis in den verknüpften Artikel
     * (Freigabe). Serverseitig neu berechnet — kein Client-Wert.
     *
     * @return array{rule: PricingMarginRule, price: string, margin: float, below_min: bool}
     *
     * @throws RuntimeException Wenn der Artikel nicht verknüpft ist oder kein Vorschlag entsteht.
     */
    public function applyToArticle(SupplierCatalogItem $item): array {
        if ($item->article_id === null) {
            throw new RuntimeException((string) __('procurement.margin.error.not_linked'));
        }

        $suggestion = $this->suggestForItem($item);
        if ($suggestion === null) {
            throw new RuntimeException((string) __('procurement.margin.error.no_suggestion'));
        }

        $article = Article::query()->find($item->article_id);
        if (! $article instanceof Article) {
            throw new RuntimeException((string) __('procurement.margin.error.no_suggestion'));
        }

        $article->default_sale_price = Money::of((string) $suggestion['price'], $article->currency ?? CurrencyCode::Euro, 4);
        $article->save();

        return $suggestion;
    }
}
