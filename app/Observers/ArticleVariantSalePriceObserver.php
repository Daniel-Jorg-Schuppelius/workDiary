<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleVariantSalePriceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\{ArticleSalePriceHistory, ArticleVariant};
use Illuminate\Support\Carbon;

/**
 * Varianten-Pendant zum {@see ArticleSalePriceObserver} (Feature 107, W10):
 * historisiert den Varianten-Verkaufspreis (`sale_price`) je SKU.
 */
class ArticleVariantSalePriceObserver {
    public function created(ArticleVariant $variant): void {
        if ($variant->sale_price !== null) {
            $this->record($variant);
        }
    }

    public function updated(ArticleVariant $variant): void {
        if ($variant->wasChanged('sale_price') && $variant->sale_price !== null) {
            $this->record($variant);
        }
    }

    private function record(ArticleVariant $variant): void {
        $organizationId = $variant->organization_id ?? $variant->article?->organization_id;
        if ($organizationId === null) {
            return;
        }
        ArticleSalePriceHistory::query()->create([
            'organization_id' => $organizationId,
            'article_id' => $variant->article_id,
            'article_variant_id' => $variant->id,
            'sale_price' => $variant->sale_price,
            'currency' => $variant->currency->value ?? 'EUR',
            'recorded_at' => Carbon::now(),
        ]);
    }
}
