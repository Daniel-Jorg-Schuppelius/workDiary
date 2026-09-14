<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Indexing\Sources;

use App\Enums\Search\SearchSourceType;
use App\Models\Scopes\OrganizationScope;
use App\Models\{ServiceTicket, ServiceTicketMessage};
use App\Services\Search\Indexing\{SearchContext, SearchDocumentData};
use Illuminate\Database\Eloquent\{Builder, Model};

/** Tickets samt Lösung, Workaround und Konversation (Mail-HTML als Text). */
final class ServiceTicketSource extends AbstractSearchSource {
    /** Obergrenze der eingelesenen Nachrichten je Ticket. */
    private const MAX_MESSAGES = 200;

    public function type(): SearchSourceType {
        return SearchSourceType::ServiceTicket;
    }

    protected function scope(Builder $query): Builder {
        return $query->with(['asset' => static fn($q) => $q->withoutGlobalScopes()->select(['id', 'name', 'asset_no', 'customer_id', 'foreign_customer_id'])]);
    }

    public function build(Model $model, SearchContext $context): ?SearchDocumentData {
        /** @var ServiceTicket $model */
        $organizationId = self::organizationOf($model);
        if ($organizationId === null) {
            return null;
        }

        $asset = $model->asset;
        $ref = $context->resolve(
            $organizationId,
            self::intOrNull($model->project_id),
            self::intOrNull($asset?->foreign_customer_id),
            self::intOrNull($model->customer_id ?? $asset?->customer_id),
        );

        $messageTexts = [];
        $messages = ServiceTicketMessage::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('service_ticket_id', $model->id)
            ->orderBy('id')
            ->limit(self::MAX_MESSAGES)
            ->get(['subject', 'body']);
        foreach ($messages as $message) {
            $messageTexts[] = $message->subject;
            $messageTexts[] = self::plain($message->body);
        }

        $description = self::plain($model->description);

        return new SearchDocumentData(
            organizationId: $organizationId,
            type: $this->type(),
            sourceId: (int) $model->id,
            title: self::join(' · ', $model->ticket_no, $model->title),
            excerpt: $description !== '' ? $description : null,
            primaryTexts: [$model->ticket_no, $model->title],
            texts: [
                $description,
                $model->resolution_summary,
                $model->workaround,
                ...$messageTexts,
                $asset?->name,
                $asset?->asset_no,
                $context->userName(self::intOrNull($model->reported_by_user_id)),
                $context->userName(self::intOrNull($model->assigned_to_user_id)),
                ...$ref->texts,
            ],
            occurredAt: $model->reported_at ?? $model->created_at,
            userId: self::intOrNull($model->reported_by_user_id),
            assignedUserId: self::intOrNull($model->assigned_to_user_id),
            customerId: $ref->customerId,
            foreignCustomerId: $ref->foreignCustomerId,
            projectId: $ref->projectId,
            restricted: $model->confidentiality === 'restricted',
            sourceUpdatedAt: $model->updated_at,
        );
    }
}
