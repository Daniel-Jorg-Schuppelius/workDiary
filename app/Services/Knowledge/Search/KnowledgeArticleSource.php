<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Search;

use App\Enums\Knowledge\ArticleStatus;
use App\Enums\Search\SearchSourceType;
use App\Models\Knowledge\KnowledgeArticle;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use App\Services\Search\Indexing\Sources\AbstractSearchSource;
use Illuminate\Database\Eloquent\{Builder, Model};

/** Wissensartikel mit Problem und Lösung; Entwürfe tragen `restricted`. */
final class KnowledgeArticleSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::KnowledgeArticle;
    }

    protected function scope(Builder $query): Builder {
        return $query->with(['tags:id,name']);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var KnowledgeArticle $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null || $model->trashed()) {
            return null;
        }

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: trim((string) $model->title),
            excerpt: self::join("\n\n", $model->problem, $model->solution) ?: null,
            primaryTexts: [$model->title],
            texts: [
                $model->problem,
                $model->solution,
                ...self::strings($model->tags, 'name'),
                $context->userName(self::intOrNull($model->created_by_user_id)),
            ],
            occurredAt: $model->published_at ?? $model->created_at,
            userId: self::intOrNull($model->created_by_user_id),
            restricted: $model->status !== ArticleStatus::Published,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
