<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntrySource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\Time\TimeEntry;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Zeiteinträge — die Hauptquelle „was wurde gemacht": manuelle Buchungen,
 * Stundenzettel-Positionen, Toggl-Importe und Fernwartungssitzungen
 * („Gerät (Sitzungsnotiz)").
 */
final class TimeEntrySource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::TimeEntry;
    }

    protected function scope(Builder $query): Builder {
        return $query->with(['tags:id,name', 'activityCategory:id,label', 'timesheet:id,notes', 'diaryEntry:id,title']);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var TimeEntry $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null) {
            return null;
        }

        $ref = $context->resolve($organizationId, self::intOrNull($model->project_id));
        $description = trim((string) $model->description);

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: self::firstLine($description),
            excerpt: $description !== '' ? $description : null,
            primaryTexts: [$description],
            texts: [
                ...self::strings($model->tags, 'name'),
                $model->activityCategory?->label,
                $model->diaryEntry?->title,
                $model->timesheet?->notes,
                $context->userName(self::intOrNull($model->user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->started_at ?? $model->date,
            dateOnly: $model->started_at === null,
            userId: self::intOrNull($model->user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            minutes: self::intOrNull($model->minutes),
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
