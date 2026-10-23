<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntrySource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Search;

use App\Enums\Search\SearchSourceType;
use App\Models\Diary\DiaryEntry;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use App\Services\Search\Indexing\Sources\AbstractSearchSource;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\{Builder, Model};

/** Aufträge samt Rückmeldung, Notizen und Kommentaren. */
final class DiaryEntrySource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::DiaryEntry;
    }

    protected function scope(Builder $query): Builder {
        return $query->with(['tags:id,name', 'entryType:id,label', 'comments:id,commentable_id,commentable_type,body']);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var DiaryEntry $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null) {
            return null;
        }

        $ref = $context->resolve($organizationId, NumberHelper::toIntOrNull($model->project_id), null, NumberHelper::toIntOrNull($model->customer_id));
        $asset = $context->asset($organizationId, NumberHelper::toIntOrNull($model->asset_id));
        $title = trim((string) $model->title);

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: $title !== '' ? $title : self::firstLine($model->content),
            excerpt: self::join("\n\n", $model->content, $model->response, $model->notes) ?: null,
            primaryTexts: [$title],
            texts: [
                $model->content,
                $model->response,
                $model->notes,
                ...$model->customFieldTexts(),
                ...self::strings($model->comments, 'body'),
                ...self::strings($model->tags, 'name'),
                $model->entryType?->label,
                $asset?->name,
                $asset?->asset_no,
                $context->userName(NumberHelper::toIntOrNull($model->user_id)),
                $context->userName(NumberHelper::toIntOrNull($model->assigned_user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->start_at ?? $model->created_at,
            userId: NumberHelper::toIntOrNull($model->user_id),
            assignedUserId: NumberHelper::toIntOrNull($model->assigned_user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
