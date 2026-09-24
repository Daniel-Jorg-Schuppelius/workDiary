<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\Diary\OpenIssue;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\Model;

/** Offene Punkte inkl. Abschlussbegründung; Kontext über das Subjekt. */
final class OpenIssueSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::OpenIssue;
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var OpenIssue $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null || $model->trashed()) {
            return null;
        }

        $ref = $context->forSubject($organizationId, $model->subject_type, self::intOrNull($model->subject_id));

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: trim((string) $model->title),
            excerpt: self::join("\n\n", $model->description, $model->closed_reason) ?: null,
            primaryTexts: [$model->title],
            texts: [
                $model->description,
                $model->closed_reason,
                $model->category,
                $context->userName(self::intOrNull($model->created_by_user_id)),
                $context->userName(self::intOrNull($model->assignee_user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->created_at,
            userId: self::intOrNull($model->created_by_user_id),
            assignedUserId: self::intOrNull($model->assignee_user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
