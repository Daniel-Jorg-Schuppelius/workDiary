<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Integration\ExternalArticleMapping;
use App\Models\Plugins\Lexoffice\LexofficeArticle;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Article\Catalog\LocalArticleCatalogSource;
use App\Services\Platform\Catalog\{ArticleCatalog, ArticleCatalogSource, CatalogArticle};
use App\Support\Sqid;

/**
 * Lexoffice-Artikel als Katalogquelle `lex` (MVP-1025). Vorrang vor dem
 * Artikelstamm beim Namensabgleich: bei Lexoffice-Hoheit stehen die
 * Abo-Produkte dort.
 */
final class LexofficeArticleCatalogSource implements ArticleCatalogSource {
    public const PREFIX = 'lex';

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'Lexoffice';
    }

    public function priority(): int {
        return 10;
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
            ->active()
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
        return Sqid::decode(LexofficeArticle::class, $token);
    }

    /**
     * Lexoffice-Artikel-ID zu einem Katalogschlüssel (MVP-1026): `lex:` direkt,
     * `art:` über die Artikelzuordnung des Syncs, Standardvariante zuerst.
     * Andere Quellen kennt Lexoffice nicht — dann ohne Artikelbezug buchen.
     */
    public function externalId(int $organizationId, ?string $articleRef): ?string {
        $ref = ArticleCatalog::parse($articleRef);
        $external = match ($ref[0] ?? null) {
            self::PREFIX => $this->query($organizationId)->whereKey($ref[1])->value('external_id'),
            LocalArticleCatalogSource::PREFIX => ExternalArticleMapping::query()
                ->withoutGlobalScopes()
                ->leftJoin('article_variants', 'article_variants.id', '=', 'external_article_mappings.article_variant_id')
                ->where('external_article_mappings.organization_id', $organizationId)
                ->where('external_article_mappings.plugin_id', LexofficePlugin::ID)
                ->where('external_article_mappings.article_id', $ref[1])
                ->orderByDesc('article_variants.is_default')
                ->orderBy('external_article_mappings.id')
                ->value('external_article_mappings.external_id'),
            default => null,
        };

        return is_string($external) && $external !== '' ? $external : null;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<LexofficeArticle> */
    private function query(int $organizationId) {
        return LexofficeArticle::query()->withoutGlobalScopes()->where('organization_id', $organizationId);
    }

    private function toCatalog(LexofficeArticle $article): CatalogArticle {
        return new CatalogArticle(
            key: ArticleCatalog::key(self::PREFIX, (int) $article->id),
            // Kein HasSqid am Spiegelmodell (Routen binden über die ID) — Sqid direkt.
            formKey: self::PREFIX . ':' . Sqid::encode(LexofficeArticle::class, (int) $article->id),
            source: self::PREFIX,
            sourceLabel: $this->label(),
            id: (int) $article->id,
            name: (string) $article->name,
            number: $article->article_number,
            unitName: $article->unit_name,
            netPrice: $article->net_unit_price,
            active: $article->archived_at === null,
            description: $article->description,
            vatRate: $article->vat_rate,
            // Lexoffice-Artikeltyp PRODUCT wird als Material-Position gebucht.
            itemType: strtolower((string) $article->type) === 'product' ? 'material' : 'service',
        );
    }
}
