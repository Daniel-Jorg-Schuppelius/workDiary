<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogLinkService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, ArticleSupply, ArticleVariant, SupplierCatalogItem};
use App\Services\Integration\Match\Normalize;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verknüpft externe Katalogartikel mit dem internen Artikelstamm (Feature 050,
 * MVP-093). Beim verbindlichen Verknüpfen entsteht/aktualisiert sich eine
 * Bezugsquelle ({@see ArticleSupply}) — damit wird der Katalog-Einkaufspreis
 * kalkulations- und beschaffungswirksam. Der Artikelstamm wird nie überschrieben,
 * nur die Bezugsquelle gepflegt.
 */
class CatalogLinkService {
    /** Schwelle des Namens-Fuzzy (wie {@see \App\Services\Integration\Match\FuzzyField}). */
    private const FUZZY_THRESHOLD = 0.86;

    /**
     * Verknüpft verbindlich und pflegt die Bezugsquelle des Lieferanten.
     *
     * @throws RuntimeException Bei org-fremdem Artikel oder unpassender Variante.
     */
    public function link(SupplierCatalogItem $item, Article $article, ?ArticleVariant $variant = null): SupplierCatalogItem {
        if ($article->organization_id !== $item->organization_id) {
            throw new RuntimeException((string) __('procurement.catalog.error.foreign_article'));
        }
        if ($variant !== null && $variant->article_id !== $article->id) {
            throw new RuntimeException((string) __('procurement.catalog.error.variant_mismatch'));
        }

        return DB::transaction(function () use ($item, $article, $variant): SupplierCatalogItem {
            $item->forceFill([
                'article_id' => $article->id,
                'article_variant_id' => $variant?->id,
                'status' => CatalogItemStatus::Linked->value,
            ])->save();

            $this->upsertSupply($item, $article);

            return $item;
        });
    }

    /** Hebt die Verknüpfung auf; die Bezugsquelle bleibt bewusst bestehen. */
    public function unlink(SupplierCatalogItem $item): SupplierCatalogItem {
        $item->forceFill([
            'article_id' => null,
            'article_variant_id' => null,
            'status' => CatalogItemStatus::New->value,
        ])->save();

        return $item;
    }

    /**
     * Schlägt anhand der EAN/GTIN einen internen Artikel vor (Status „Proposed",
     * noch nicht verbindlich). Liefert den getroffenen Artikel oder null.
     */
    public function propose(SupplierCatalogItem $item): ?Article {
        // Kaskade (MVP-541): GTIN → Varianten-SKU → Bezugsquellen-SKU →
        // eindeutiger Namens-Fuzzy. Dienstleistungen haben meist keine GTIN.
        [$article, $variant] = $this->proposeByGtin($item)
            ?? $this->proposeByVariantSku($item)
            ?? $this->proposeBySupplySku($item)
            ?? $this->proposeByFuzzyName($item)
            ?? [null, null];

        if (! $article instanceof Article) {
            return null;
        }

        $item->forceFill([
            'article_id' => $article->id,
            'article_variant_id' => $variant?->id,
            'status' => CatalogItemStatus::Proposed->value,
        ])->save();

        return $article;
    }

    /** @return array{0: Article, 1: ArticleVariant|null}|null */
    private function proposeByGtin(SupplierCatalogItem $item): ?array {
        $gtin = (string) ($item->gtin ?? '');
        if ($gtin === '') {
            return null;
        }

        $article = $this->purchasableArticles($item)->where('gtin', $gtin)->first();

        return $article instanceof Article ? [$article, null] : null;
    }

    /**
     * Varianten-SKU == externe Artikelnummer (z. B. übernommener Offer-Key).
     *
     * @return array{0: Article, 1: ArticleVariant|null}|null
     */
    private function proposeByVariantSku(SupplierCatalogItem $item): ?array {
        $externalNo = trim((string) $item->external_no);
        if ($externalNo === '') {
            return null;
        }

        $variant = ArticleVariant::query()
            ->where('organization_id', $item->organization_id)
            ->where('sku', $externalNo)
            ->whereHas('article', fn ($q) => $q->where('purchasable', true))
            ->with('article')
            ->first();

        return $variant?->article instanceof Article ? [$variant->article, $variant] : null;
    }

    /**
     * Bereits gepflegte Bezugsquelle desselben Lieferanten.
     *
     * @return array{0: Article, 1: ArticleVariant|null}|null
     */
    private function proposeBySupplySku(SupplierCatalogItem $item): ?array {
        $externalNo = trim((string) $item->external_no);
        if ($externalNo === '') {
            return null;
        }

        $supply = ArticleSupply::query()
            ->where('organization_id', $item->organization_id)
            ->where('supplier_id', $item->supplier_id)
            ->where('supplier_sku', $externalNo)
            ->with('article')
            ->first();

        $article = $supply?->article;

        return $article instanceof Article && $article->purchasable ? [$article, null] : null;
    }

    /**
     * Namensähnlichkeit — nur bei GENAU einem Treffer (kein Rätselraten).
     *
     * @return array{0: Article, 1: ArticleVariant|null}|null
     */
    private function proposeByFuzzyName(SupplierCatalogItem $item): ?array {
        $needle = Normalize::text($item->name);
        if ($needle === '') {
            return null;
        }

        $matches = $this->purchasableArticles($item)
            ->get(['id', 'name'])
            ->filter(fn (Article $a): bool => Normalize::similarity($needle, Normalize::text($a->name)) >= self::FUZZY_THRESHOLD);

        $match = $matches->count() === 1 ? $matches->first() : null;
        if (! $match instanceof Article) {
            return null;
        }

        /** @var Article $article */
        $article = Article::query()->findOrFail($match->id);

        return [$article, null];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Article> */
    private function purchasableArticles(SupplierCatalogItem $item): \Illuminate\Database\Eloquent\Builder {
        return Article::query()
            ->where('organization_id', $item->organization_id)
            ->where('purchasable', true);
    }

    /** Legt die Bezugsquelle des Lieferanten an oder aktualisiert ihre Katalogdaten. */
    private function upsertSupply(SupplierCatalogItem $item, Article $article): void {
        $supply = ArticleSupply::query()->firstOrNew([
            'organization_id' => $item->organization_id,
            'article_id' => $article->id,
            'supplier_id' => $item->supplier_id,
        ]);

        $supply->supplier_sku = $item->external_no;
        // MVP-564: Rohstoffzuschläge (Kupfer & Co.) fließen in den effektiven EK,
        // sofern eine Metallnotierung gepflegt ist — sonst bleibt der Basispreis.
        $supply->purchase_price = app(MetalSurchargeService::class)->effectivePurchasePrice($item) ?? $item->purchase_price;
        $supply->currency = $item->currency ?? \CommonToolkit\Enums\CurrencyCode::Euro;
        $supply->pack_size = $item->pack_size ?? '1';
        $supply->lead_time_days = $item->lead_time_days ?? 0;

        // Manuell gepflegte Felder (MOQ/Vorzugslieferant) bei Erstanlage defaulten,
        // bei Bestand nicht überschreiben.
        if (! $supply->exists) {
            $supply->moq = '1';
            $supply->is_preferred = false;
        }

        $supply->save();
    }
}
