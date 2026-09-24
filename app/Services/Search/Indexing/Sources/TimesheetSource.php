<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\Time\Timesheet;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Stundenzettel-Notizen. Die Positionen selbst sind Zeiteinträge und stehen
 * dort; ein Zettel ohne Notiz bekommt kein eigenes Dokument.
 */
final class TimesheetSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::Timesheet;
    }

    protected function scope(Builder $query): Builder {
        return $query->whereNotNull('notes')->where('notes', '<>', '');
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var Timesheet $model */
        $organizationId = self::organizationOf($model);
        $notes = trim((string) $model->notes);
        if ($organizationId === null || $notes === '') {
            return null;
        }

        $ref = $context->resolve($organizationId, self::intOrNull($model->project_id));

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: self::firstLine($notes),
            excerpt: $notes,
            primaryTexts: [$notes],
            texts: [
                $model->customer_name,
                $model->customer_role,
                $context->userName(self::intOrNull($model->user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->work_date,
            dateOnly: true,
            userId: self::intOrNull($model->user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
