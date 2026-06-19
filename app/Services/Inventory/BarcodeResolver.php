<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BarcodeResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\BarcodeMatchType;
use App\Models\{Article, ArticleVariant, StockLot, StockSerial};

/**
 * Löst einen gescannten Code (Feature 048, E5) zur passenden Entität auf:
 * Seriennummer → Charge → Varianten-GTIN/SKU → Artikel-GTIN. Liefert immer die
 * bestandsführende Variante mit, sofern bestimmbar. Alle Abfragen sind
 * organisationsweit gescoped (BelongsToOrganization).
 */
class BarcodeResolver {
    public function resolve(string $code): BarcodeMatch {
        $code = trim($code);
        if ($code === '') {
            return new BarcodeMatch(BarcodeMatchType::Unknown);
        }

        $serial = StockSerial::query()->where('serial_no', $code)->first();
        if ($serial instanceof StockSerial) {
            $variant = $serial->variant;

            return new BarcodeMatch(
                BarcodeMatchType::Serial,
                variant: $variant instanceof ArticleVariant ? $variant : null,
                serial: $serial,
                article: $serial->article,
            );
        }

        $lot = StockLot::query()->where('lot_no', $code)->first();
        if ($lot instanceof StockLot) {
            $variant = $lot->variant;

            return new BarcodeMatch(
                BarcodeMatchType::Lot,
                variant: $variant instanceof ArticleVariant ? $variant : null,
                lot: $lot,
            );
        }

        $variant = ArticleVariant::query()
            ->where(fn ($q) => $q->where('gtin', $code)->orWhere('sku', $code))
            ->first();
        if ($variant instanceof ArticleVariant) {
            return new BarcodeMatch(BarcodeMatchType::Variant, variant: $variant, article: $variant->article);
        }

        $article = Article::query()->where('gtin', $code)->first();
        if ($article instanceof Article) {
            return new BarcodeMatch(BarcodeMatchType::Article, variant: $this->defaultVariant($article), article: $article);
        }

        return new BarcodeMatch(BarcodeMatchType::Unknown);
    }

    private function defaultVariant(Article $article): ?ArticleVariant {
        return ArticleVariant::query()
            ->where('article_id', $article->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
