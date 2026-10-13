<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Relations;

use App\Enums\Billing\{DocumentKind, DocumentRelationKind};
use App\Models\Billing\{DeliveryNote, DeliveryNoteItem};
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Sales\OrderConfirmation;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};
use App\Services\Invoicing\Relations\InvoiceNode;
use Illuminate\Support\Facades\Gate;

/**
 * Lieferschein im Belegverbund (MVP-1157/1163): AB bzw. Fertigungsauftrag →
 * Lieferschein → Rechnung, die seine Lagerauslieferung abrechnet.
 */
final class DeliveryNoteRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        $notes = DeliveryNote::query()->where('organization_id', $organizationId);
        if ($document->is(OrderConfirmation::class)) {
            return $this->notes($notes->where('order_confirmation_id', $document->id)->get(), DocumentRelationKind::FollowUp);
        }
        if ($document->is(ManufacturingOrder::class)) {
            return $this->notes($notes->where('manufacturing_order_id', $document->id)->get(), DocumentRelationKind::FollowUp);
        }
        if ($document->is(Invoice::class)) {
            $deliveryIds = InvoiceItem::query()->where('organization_id', $organizationId)->where('invoice_id', $document->id)->whereNotNull('stock_delivery_id')->pluck('stock_delivery_id');
            $noteIds = DeliveryNoteItem::query()->where('organization_id', $organizationId)->whereIn('stock_delivery_id', $deliveryIds)->pluck('delivery_note_id');

            return $this->notes($notes->whereIn('id', $noteIds)->get(), DocumentRelationKind::Source);
        }
        if (! $document->is(DeliveryNote::class)) {
            return [];
        }
        $note = $notes->with('items')->find($document->id);
        if (! $note instanceof DeliveryNote) {
            return [];
        }

        $relations = [];
        $confirmation = $note->order_confirmation_id !== null
            ? OrderConfirmation::query()->where('organization_id', $organizationId)->find($note->order_confirmation_id)
            : null;
        if ($confirmation instanceof OrderConfirmation) {
            $relations[] = new DocumentRelation(new DocumentNode(
                ref: DocumentRef::forModel($confirmation),
                label: DocumentKind::OrderConfirmation->label(),
                number: $confirmation->number,
                date: $confirmation->confirmed_on ?? $confirmation->created_at,
                state: $confirmation->status->label(),
                url: Gate::allows('view', $confirmation) ? route('order-confirmations.show', $confirmation) : null,
            ), DocumentRelationKind::Source);
        }
        $order = $note->manufacturing_order_id !== null
            ? ManufacturingOrder::query()->where('organization_id', $organizationId)->find($note->manufacturing_order_id)
            : null;
        if ($order instanceof ManufacturingOrder) {
            $relations[] = new DocumentRelation(new DocumentNode(
                ref: DocumentRef::forModel($order),
                label: (string) __('billing.relations.manufacturing_order'),
                number: $order->number,
                date: $order->created_at,
                state: $order->status->label(),
                url: Gate::allows('view', $order) ? route('manufacturing-orders.show', $order) : null,
            ), DocumentRelationKind::Source);
        }
        $deliveryIds = $note->items->pluck('stock_delivery_id')->filter();
        $invoiceIds = $deliveryIds->isEmpty() ? collect() : InvoiceItem::query()->where('organization_id', $organizationId)->whereIn('stock_delivery_id', $deliveryIds)->distinct()->pluck('invoice_id');
        foreach (Invoice::query()->where('organization_id', $organizationId)->whereIn('id', $invoiceIds)->get() as $invoice) {
            $relations[] = new DocumentRelation(InvoiceNode::of($invoice), DocumentRelationKind::FollowUp);
        }

        return $relations;
    }

    public static function node(DeliveryNote $note): DocumentNode {
        return new DocumentNode(
            ref: DocumentRef::forModel($note),
            label: DocumentKind::DeliveryNote->label(),
            number: $note->number,
            date: $note->delivered_on ?? $note->created_at,
            state: $note->status->label(),
            url: Gate::allows('view', $note) ? route('delivery-notes.show', $note) : null,
        );
    }

    /**
     * @param  iterable<DeliveryNote>  $notes
     * @return list<DocumentRelation>
     */
    private function notes(iterable $notes, DocumentRelationKind $kind): array {
        $relations = [];
        foreach ($notes as $note) {
            $relations[] = new DocumentRelation(self::node($note), $kind);
        }

        return $relations;
    }
}
