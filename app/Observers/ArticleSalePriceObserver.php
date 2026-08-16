<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleSalePriceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\{Article, ArticleSalePriceHistory};
use Illuminate\Support\Carbon;

/**
 * Historisiert den Verkaufspreis eines Artikels (Feature 107, W10): bei
 * Anlage mit Preis und bei jeder Änderung von `default_sale_price` entsteht
 * ein Verlaufseintrag — Grundlage des DATPREIS-Exports „Änderungen seit".
 */
class ArticleSalePriceObserver {
    public function created(Article $article): void {
        if ($article->default_sale_price !== null) {
            $this->record($article);
        }
    }

    public function updated(Article $article): void {
        if ($article->wasChanged('default_sale_price') && $article->default_sale_price !== null) {
            $this->record($article);
        }
    }

    private function record(Article $article): void {
        ArticleSalePriceHistory::query()->create([
            'organization_id' => $article->organization_id,
            'article_id' => $article->id,
            'article_variant_id' => null,
            'sale_price' => $article->default_sale_price,
            'currency' => $article->currency->value,
            'recorded_at' => Carbon::now(),
        ]);
    }
}
