<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article\Catalog;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Models\Article\Article;
use App\Services\Platform\Catalog\{ArticleCatalog, ArticleCatalogSource, CatalogArticle};
use App\Support\Sqid;

/** Der Artikelstamm als Katalogquelle `art` (MVP-1025), angemeldet über das Artikel-Manifest. */
final class LocalArticleCatalogSource implements ArticleCatalogSource {
    public const PREFIX = 'art';

    /** Artikelstamm-ID aus einem Katalogschlüssel, null für andere Quellen. */
    public static function idOf(?string $articleRef): ?int {
        $parsed = ArticleCatalog::parse($articleRef);

        return $parsed !== null && $parsed[0] === self::PREFIX ? $parsed[1] : null;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return (string) __('article.catalog.source_local');
    }

    public function priority(): int {
        return 20;
    }

    public function find(int $organizationId, array $ids): array {
        $found = [];
        foreach ($this->query($organizationId)->whereKey($ids)->get() as $article) {
            $found[(int) $article->id] = $this->toCatalog($article);
        }

        return $found;
    }

    public function active(int $organizationId): array {
        $query = $this->query($organizationId)
            ->where('status', ArticleStatus::Active->value)
            ->where('sellable', true)
            ->orderBy('name')
            ->limit(2000)
            ->get();
        $articles = [];
        foreach ($query as $article) {
            $articles[] = $this->toCatalog($article);
        }

        return $articles;
    }

    public function decode(string $token): ?int {
        return Sqid::decode(Article::class, $token);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Article> */
    private function query(int $organizationId) {
        return Article::query()->withoutGlobalScopes()->where('organization_id', $organizationId)
            ->select(['id', 'organization_id', 'number', 'name', 'description', 'type', 'base_unit', 'default_sale_price', 'currency', 'status']);
    }

    private function toCatalog(Article $article): CatalogArticle {
        return new CatalogArticle(
            key: ArticleCatalog::key(self::PREFIX, (int) $article->id),
            formKey: self::PREFIX . ':' . $article->sqid,
            source: self::PREFIX,
            sourceLabel: $this->label(),
            id: (int) $article->id,
            name: (string) $article->name,
            number: $article->number,
            unitName: $article->base_unit,
            netPrice: $article->default_sale_price,
            active: $article->status === ArticleStatus::Active,
            description: $article->description,
            itemType: $article->type === ArticleType::Service ? 'service' : 'material',
        );
    }
}
