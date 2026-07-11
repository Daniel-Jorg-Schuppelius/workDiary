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
        $gtin = (string) ($item->gtin ?? '');
        if ($gtin === '') {
            return null;
        }

        $article = Article::query()
            ->where('organization_id', $item->organization_id)
            ->where('gtin', $gtin)
            ->where('purchasable', true)
            ->first();

        if (! $article instanceof Article) {
            return null;
        }

        $item->forceFill([
            'article_id' => $article->id,
            'status' => CatalogItemStatus::Proposed->value,
        ])->save();

        return $article;
    }

    /** Legt die Bezugsquelle des Lieferanten an oder aktualisiert ihre Katalogdaten. */
    private function upsertSupply(SupplierCatalogItem $item, Article $article): void {
        $supply = ArticleSupply::query()->firstOrNew([
            'organization_id' => $item->organization_id,
            'article_id' => $article->id,
            'supplier_id' => $item->supplier_id,
        ]);

        $supply->supplier_sku = $item->external_no;
        $supply->purchase_price = $item->purchase_price;
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
