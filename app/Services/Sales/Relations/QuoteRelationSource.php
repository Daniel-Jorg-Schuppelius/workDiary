<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Relations;

use App\Enums\Billing\DocumentRelationKind;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};
use App\Services\Invoicing\Relations\InvoiceNode;
use Illuminate\Support\Facades\Gate;

/** Angebot im Belegverbund (MVP-1157): Versionen, Auftragsbestätigungen und Rechnungen daraus. */
final class QuoteRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        if ($document->is(OrderConfirmation::class)) {
            $quoteId = OrderConfirmation::query()->where('organization_id', $organizationId)->whereKey($document->id)->value('quote_id');
            $quote = $quoteId !== null ? Quote::query()->where('organization_id', $organizationId)->find($quoteId) : null;

            return $quote instanceof Quote ? [new DocumentRelation(self::node($quote), DocumentRelationKind::Source)] : [];
        }
        if ($document->is(Invoice::class)) {
            $quoteId = Invoice::query()->where('organization_id', $organizationId)->whereKey($document->id)->value('quote_id');
            $quote = $quoteId !== null ? Quote::query()->where('organization_id', $organizationId)->find($quoteId) : null;

            return $quote instanceof Quote ? [new DocumentRelation(self::node($quote), DocumentRelationKind::Source)] : [];
        }
        if (! $document->is(Quote::class)) {
            return [];
        }
        $quote = Quote::query()->where('organization_id', $organizationId)->find($document->id);
        if (! $quote instanceof Quote) {
            return [];
        }

        $relations = [];
        $versions = Quote::query()->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->where('previous_version_id', $quote->id)->orWhere('id', $quote->previous_version_id ?? 0))
            ->get();
        foreach ($versions as $version) {
            $relations[] = new DocumentRelation(self::node($version), DocumentRelationKind::Version);
        }
        foreach (OrderConfirmation::query()->where('organization_id', $organizationId)->where('quote_id', $quote->id)->get() as $confirmation) {
            $relations[] = new DocumentRelation(self::confirmationNode($confirmation), DocumentRelationKind::FollowUp);
        }
        foreach (Invoice::query()->where('organization_id', $organizationId)->where('quote_id', $quote->id)->get() as $invoice) {
            $relations[] = new DocumentRelation(InvoiceNode::of($invoice), DocumentRelationKind::FollowUp);
        }

        return $relations;
    }

    /** Auftragsbestätigung als Knoten (Phase 145, MVP-1162). */
    public static function confirmationNode(OrderConfirmation $confirmation): DocumentNode {
        return new DocumentNode(
            ref: DocumentRef::forModel($confirmation),
            label: (string) __('Auftragsbestätigung'),
            number: $confirmation->number,
            date: $confirmation->confirmed_on ?? $confirmation->created_at,
            state: $confirmation->status->label(),
            url: Gate::allows('view', $confirmation) ? route('order-confirmations.show', $confirmation) : null,
        );
    }

    private static function node(Quote $quote): DocumentNode {
        return new DocumentNode(
            ref: DocumentRef::forModel($quote),
            label: (string) __('billing.relations.quote_version', ['version' => $quote->version]),
            number: $quote->number,
            date: $quote->created_at,
            state: $quote->status->label(),
            url: Gate::allows('view', $quote) ? route('quotes.show', $quote) : null,
        );
    }
}
