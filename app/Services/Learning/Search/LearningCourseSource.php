<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Learning\LearningCourseStatus;
use App\Enums\Search\SearchSourceType;
use App\Models\Learning\LearningCourse;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Freigegebene Lernkurse (Feature 149, MVP-789): Titel, Untertitel,
 * Beschreibung, Lernziele und Kategorie. Entwürfe und archivierte Kurse
 * fallen aus dem Index (der Scope liefert sie nicht — der Indexer entfernt
 * fehlende Zeilen). Wer den Treffer sieht, regelt `ActivitySearchVisibility`:
 * eigene Einschreibung oder `learning.viewAny`.
 */
final class LearningCourseSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::LearningCourse;
    }

    protected function scope(Builder $query): Builder {
        return $query
            ->with(['category:id,name', 'tags:id,name'])
            ->where('status', LearningCourseStatus::Released->value);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var LearningCourse $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null || $model->status !== LearningCourseStatus::Released) {
            return null;
        }

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: trim((string) $model->title),
            excerpt: self::join("\n\n", $model->subtitle, $model->description) ?: null,
            primaryTexts: [$model->title, $model->subtitle],
            texts: [
                $model->description,
                $model->objectives,
                $model->code,
                $model->category->name ?? null,
                ...self::strings($model->tags, 'name'),
                $context->userName(self::intOrNull($model->owner_user_id)),
            ],
            occurredAt: $model->updated_at,
            dateOnly: true,
            userId: self::intOrNull($model->owner_user_id),
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
