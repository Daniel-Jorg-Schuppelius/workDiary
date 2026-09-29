<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleArticleCandidates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\ResaleArticleRole;
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use App\Services\Reselling\Marketplace\ProductNameMatcher;

/**
 * Kandidaten für den Namensabgleich neuer Abos (Marketplace-Import,
 * Domain-Abgleich; MVP-1025): aktive Artikel aller Katalogquellen nach
 * Vorrang, ohne „nie Abo"-Einstufung. Steht derselbe Name in mehreren Quellen
 * (z. B. Artikelstamm und Lexoffice), zählt nur der vorrangige — sonst wäre
 * jeder gespiegelte Artikel mehrdeutig.
 */
final class ResaleArticleCandidates {
    public function __construct(
        private readonly ArticleCatalog $catalog,
        private readonly LicenseArticleClassifier $classifier,
    ) {}

    /** @return list<CatalogArticle> */
    public function for(int $organizationId): array {
        $seen = [];
        $candidates = [];
        foreach ($this->catalog->active($organizationId) as $article) {
            if ($this->classifier->roleOf($organizationId, $article->key) === ResaleArticleRole::Excluded) {
                continue;
            }
            $name = ProductNameMatcher::normalize($article->name);
            if (! isset($seen[$name])) {
                $seen[$name] = true;
                $candidates[] = $article;
            }
        }

        return $candidates;
    }
}
