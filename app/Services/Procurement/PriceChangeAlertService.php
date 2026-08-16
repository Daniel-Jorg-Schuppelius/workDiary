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
 * fällt, entsteht eine offene Warnung. Zusätzlich entsteht bei einer
 * Verfügbarkeitsänderung eines verknüpften Artikels mit offenen Vorgängen eine
 * Verfügbarkeitswarnung. Beide Warnungen tragen die betroffenen offenen
 * Vorgänge (Bestellungen, LV-Positionen, Fertigungsaufträge) als Snapshot.
 * Verkaufspreise werden nicht automatisch geändert — die Reaktion bleibt beim
 * Anwender.
 */
class PriceChangeAlertService {
    public function __construct(
        private readonly PriceSuggestionService $pricing,
        private readonly DocumentImpactScanner $impacts,
    ) {}

    /**
     * Bewertet eine Preisänderung eines Katalogartikels und legt bei
     * Unterschreitung der Mindestmarge eine Warnung an.
     */
    public function evaluate(SupplierCatalogItem $item, ?string $oldPrice, string $newPrice): ?PricingChangeAlert {
        if ($item->article_id === null) {
            return null;
        }

        // Feature 107: Ein DATANORM-Listenpreis ohne aufgelöste Rabattgruppe ist
        // nur die EK-Obergrenze, kein echter Einkaufspreis — Margenwarnungen
        // darauf wären Fehlalarme. Sobald die RAB-Lieferung eintrifft, wird der
        // EK neu berechnet und regulär bewertet.
        if ($item->price_type === 'list'
            && $item->discount_group !== null
            && ! \App\Models\SupplierCatalogDiscountGroup::query()
                ->where('supplier_catalog_source_id', $item->supplier_catalog_source_id)
                ->where('code', $item->discount_group)
                ->exists()) {
            return null;
        }

        $article = Article::query()->find($item->article_id);
        if (! $article instanceof Article || $article->default_sale_price === null) {
            return null;
        }

        $sale = $article->default_sale_price->toFloat();
        if ($sale <= 0) {
            return null;
        }

        $newMargin = ($sale - (float) $newPrice) / $sale * 100;
        $rule = $this->pricing->resolveRule($item->organization_id, $item->supplier_id, $item->category);
        $minMargin = $rule !== null && $rule->min_margin !== null ? (float) $rule->min_margin->getNumericValue() : null;

        $below = ($minMargin !== null && $newMargin < $minMargin - 0.0001) || $newMargin < 0;
        if (! $below) {
            return null;
        }

        $impacts = $this->impacts->scan((int) $item->organization_id, (int) $article->id);

        return PricingChangeAlert::query()->create([
            'organization_id' => $item->organization_id,
            'supplier_catalog_item_id' => $item->id,
            'article_id' => $article->id,
            'supplier_id' => $item->supplier_id,
            'type' => PricingChangeAlert::TYPE_MARGIN,
            'old_purchase_price' => $oldPrice,
            'new_purchase_price' => $newPrice,
            'sale_price' => $article->default_sale_price,
            'new_margin' => round($newMargin, 3),
            'min_margin' => $minMargin,
            'impacts' => $this->impacts->isEmpty($impacts) ? null : $impacts,
            'status' => PricingChangeAlert::STATUS_OPEN,
        ]);
    }

    /**
     * Bewertet eine Verfügbarkeitsänderung eines verknüpften Katalogartikels.
     * Eine Warnung entsteht nur, wenn offene Vorgänge den Artikel referenzieren
     * (Rauschschutz) — die Änderung selbst bleibt sonst im Katalogstand
     * sichtbar.
     */
    public function evaluateAvailability(SupplierCatalogItem $item, ?string $oldAvailability, ?string $newAvailability): ?PricingChangeAlert {
        $old = trim((string) $oldAvailability);
        $new = trim((string) $newAvailability);
        if ($item->article_id === null || $old === $new) {
            return null;
        }

        $impacts = $this->impacts->scan((int) $item->organization_id, (int) $item->article_id);
        if ($this->impacts->isEmpty($impacts)) {
            return null;
        }

        $impacts['availability'] = ['old' => $old !== '' ? $old : null, 'new' => $new !== '' ? $new : null];

        return PricingChangeAlert::query()->create([
            'organization_id' => $item->organization_id,
            'supplier_catalog_item_id' => $item->id,
            'article_id' => $item->article_id,
            'supplier_id' => $item->supplier_id,
            'type' => PricingChangeAlert::TYPE_AVAILABILITY,
            'old_purchase_price' => null,
            'new_purchase_price' => null,
            'sale_price' => null,
            'new_margin' => null,
            'min_margin' => null,
            'impacts' => $impacts,
            'status' => PricingChangeAlert::STATUS_OPEN,
        ]);
    }
}
