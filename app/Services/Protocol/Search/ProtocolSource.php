<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\Protocol\Protocol;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\{Builder, Model};

/** Protokolle mit Ausgangs-/Endzustand; Kontext über das Subjekt. */
final class ProtocolSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::Protocol;
    }

    protected function scope(Builder $query): Builder {
        return $query->with(['tags:id,name']);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var Protocol $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null) {
            return null;
        }

        $ref = $context->forSubject($organizationId, $model->subject_type, self::intOrNull($model->subject_id));

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: trim((string) $model->title),
            excerpt: self::join("\n\n", $model->description, $model->state_final) ?: null,
            primaryTexts: [$model->title],
            texts: [
                $model->description,
                $model->state_initial,
                $model->state_final,
                ...self::strings($model->tags, 'name'),
                $context->userName(self::intOrNull($model->created_by_user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->occurred_at ?? $model->created_at,
            userId: self::intOrNull($model->created_by_user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
