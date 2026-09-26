<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialArticleLinker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Material;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Material\Material;

/**
 * Ordnet Materialien ohne Artikel einem Artikel zu (MVP-904), wenn ihre SKU
 * genau einer Artikelnummer oder Varianten-SKU entspricht. Mehrdeutige
 * Treffer bleiben unverknüpft; bestehende Zuordnungen werden nie geändert.
 */
final class MaterialArticleLinker {
    public function linkBySku(int $organizationId): int {
        $linked = 0;
        $materials = Material::query()
            ->where('organization_id', $organizationId)
            ->whereNull('article_id')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get();

        foreach ($materials as $material) {
            $sku = (string) $material->sku;
            $candidates = Article::query()->where('organization_id', $organizationId)->where('number', $sku)->pluck('id')
                ->merge(ArticleVariant::query()->where('organization_id', $organizationId)->where('sku', $sku)->pluck('article_id'))
                ->unique()
                ->values();
            if ($candidates->count() === 1) {
                $material->forceFill(['article_id' => (int) $candidates->first()])->save();
                $linked++;
            }
        }

        return $linked;
    }
}
