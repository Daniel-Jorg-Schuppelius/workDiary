<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSessionSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\Search;

use App\Enums\Search\SearchSourceType;
use App\Models\Auth\RemotePendingSession;
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use App\Services\Search\Indexing\Sources\AbstractSearchSource;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Noch nicht zugeordnete Fernwartungssitzungen aus der Inbox. Gebuchte
 * Sitzungen sind Zeiteinträge und stehen dort; importierte, verworfene und
 * Verbindungsversuche bekommen kein Dokument.
 */
final class RemoteSessionSource extends AbstractSearchSource {
    public function type(): SearchSourceType {
        return SearchSourceType::RemoteSession;
    }

    protected function scope(Builder $query): Builder {
        return $query->where('status', RemotePendingSession::STATUS_OPEN);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var RemotePendingSession $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null || $model->status !== RemotePendingSession::STATUS_OPEN) {
            return null;
        }

        $asset = $context->asset($organizationId, self::intOrNull($model->asset_id));
        $ref = $context->resolve($organizationId, null, self::intOrNull($asset?->foreign_customer_id), self::intOrNull($asset?->customer_id));
        $note = trim((string) $model->note);

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: self::join(' · ', $model->alias, $model->remote_id),
            excerpt: $note !== '' ? $note : null,
            primaryTexts: [$note],
            texts: [
                $model->alias,
                $model->remote_id,
                $model->provider,
                $asset?->name,
                $asset?->asset_no,
                ...$ref->texts,
            ],
            occurredAt: $model->started_at,
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            minutes: $model->minutes(),
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
