<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplySourceComparator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Article, ArticleSupply};
use Illuminate\Support\Collection;

/**
 * Vergleicht die Bezugsquellen eines Artikels und empfiehlt die günstigste
 * verfügbare (Feature 050: Lieferantenvergleich). Sortierung: Quellen mit Preis
 * vor solchen ohne, dann nach Einkaufspreis, dann kürzerer Lieferzeit, dann
 * bevorzugte Quelle.
 */
class SupplySourceComparator {
    /**
     * @return Collection<int, ArticleSupply>  beste Quelle zuerst
     */
    public function forArticle(Article $article): Collection {
        /** @var Collection<int, ArticleSupply> $supplies */
        $supplies = $article->supplies()->with('supplier')->get();

        return $supplies->sort($this->order(...))->values();
    }

    /** Empfohlene Bezugsquelle (günstigste mit Preis) oder null. */
    public function recommend(Article $article): ?ArticleSupply {
        return $this->forArticle($article)->first(fn (ArticleSupply $s): bool => $s->purchase_price !== null);
    }

    private function order(ArticleSupply $a, ArticleSupply $b): int {
        $aHasPrice = $a->purchase_price !== null;
        $bHasPrice = $b->purchase_price !== null;
        if ($aHasPrice !== $bHasPrice) {
            return $aHasPrice ? -1 : 1;
        }
        if ($aHasPrice && $bHasPrice) {
            $cmp = bccomp($a->purchase_price->getAmount(), $b->purchase_price->getAmount(), 4);
            if ($cmp !== 0) {
                return $cmp;
            }
        }
        if ($a->lead_time_days !== $b->lead_time_days) {
            return $a->lead_time_days <=> $b->lead_time_days;
        }

        return ($b->is_preferred ? 1 : 0) <=> ($a->is_preferred ? 1 : 0);
    }
}
