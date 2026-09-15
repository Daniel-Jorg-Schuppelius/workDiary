<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\CommunicationNote;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\Model;

/**
 * Kommunikationsnotizen mit Text, Ergebnis und Folgeaktion. Vertrauliche
 * Notizen tragen `restricted` — die Sicht filtert sie wie `visibleTo()`.
 */
final class CommunicationNoteSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::CommunicationNote;
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var CommunicationNote $model */
        $organizationId = self::organizationOf($model);
        // Private Notizen (MVP-789) sind persönliche Merkzettel — sie stehen in
        // „Meine Schulungen", nicht im Organisationsindex.
        if ($organizationId === null || $model->trashed() || $model->isPrivate()) {
            return null;
        }

        $ref = $context->forSubject($organizationId, $model->notable_type, self::intOrNull($model->notable_id));

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: trim((string) $model->subject),
            excerpt: self::join("\n\n", $model->body, $model->result, $model->next_action) ?: null,
            primaryTexts: [$model->subject],
            texts: [
                $model->body,
                $model->result,
                $model->next_action,
                $context->userName(self::intOrNull($model->created_by_user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->occurred_at ?? $model->created_at,
            userId: self::intOrNull($model->created_by_user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            restricted: (bool) $model->confidential,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
