<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Enums\Billing\{DocumentKind, DocumentRelationKind};
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\OrgaMax\Models\OrgaMaxInvoice;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};

/**
 * orgaMAX im Belegverbund (MVP-1158): Übergabe → Auftrag in orgaMAX →
 * daraus erzeugte Rechnung (Umwandlung über die Outbox); Gegenstücke der von
 * orgaMAX nummerierten Angebote, AB und Rechnungen (Phase 145).
 */
final class OrgaMaxRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        if ($document->is(BillingTransfer::class)) {
            $reference = ExternalReference::query()
                ->forPlugin($organizationId, OrgaMaxPlugin::ID, OrgaMaxTarget::EXT_TYPE_ORDER)
                ->where('referenceable_type', $document->type)->where('referenceable_id', $document->id)
                ->first();

            return $reference instanceof ExternalReference ? [new DocumentRelation(new DocumentNode(
                ref: DocumentRef::external(OrgaMaxPlugin::ID, 'order', (string) $reference->external_id),
                label: (string) __('orgamax::orgamax.relations.order'),
                number: $reference->external_number,
                date: $reference->synced_at,
                system: 'orgaMAX',
            ), DocumentRelationKind::Counterpart)] : [];
        }
        // Gegenstück eines von orgaMAX nummerierten Belegs (Phase 145).
        $issued = match (true) {
            $document->is(Quote::class) => [OrgaMaxDocumentIssuer::EXT_TYPE_OFFER, DocumentKind::Quote],
            $document->is(OrderConfirmation::class) => [OrgaMaxDocumentIssuer::EXT_TYPE_ORDER_CONFIRMATION, DocumentKind::OrderConfirmation],
            $document->is(Invoice::class) => [OrgaMaxDocumentIssuer::EXT_TYPE_INVOICE, DocumentKind::Invoice],
            default => null,
        };
        if ($issued !== null) {
            $reference = ExternalReference::query()
                ->forPlugin($organizationId, OrgaMaxPlugin::ID, $issued[0])
                ->where('referenceable_type', $document->type)->where('referenceable_id', $document->id)
                ->first();

            return $reference instanceof ExternalReference ? [new DocumentRelation(new DocumentNode(
                ref: DocumentRef::external(OrgaMaxPlugin::ID, $issued[1]->value, (string) $reference->external_id),
                label: $issued[1]->label(),
                number: $reference->external_number,
                date: $reference->synced_at,
                system: 'orgaMAX',
            ), DocumentRelationKind::Counterpart)] : [];
        }
        if (! $document->isExternal(OrgaMaxPlugin::ID, 'order')) {
            return [];
        }

        $converted = ExternalReference::query()
            ->forPlugin($organizationId, OrgaMaxPlugin::ID, 'orgamax_converted_invoice')
            ->forExternalId('order:' . $document->id)
            ->first();
        $invoiceId = (string) ($converted?->payload['invoice_id'] ?? '');
        if ($invoiceId === '') {
            return [];
        }
        $mirror = OrgaMaxInvoice::query()->where('organization_id', $organizationId)->where('external_id', $invoiceId)->first();
        $number = $mirror->invoice_number ?? (string) ($converted?->payload['invoice_number'] ?? '');

        return [new DocumentRelation(new DocumentNode(
            ref: DocumentRef::external(OrgaMaxPlugin::ID, 'invoice', $invoiceId),
            label: DocumentKind::Invoice->label(),
            number: $number !== '' ? $number : null,
            date: $mirror?->invoice_date,
            state: $mirror?->invoice_status?->label(),
            system: 'orgaMAX',
        ), DocumentRelationKind::FollowUp)];
    }
}
