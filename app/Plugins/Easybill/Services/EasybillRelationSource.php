<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Billing\{DocumentKind, DocumentRelationKind};
use App\Models\Billing\DeliveryNote;
use App\Models\Finance\{AccountingVoucher, BillingTransfer};
use App\Models\Integration\ExternalReference;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Easybill\EasybillPlugin;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};
use App\Services\Finance\Relations\AccountingVoucherNode;

/** easybill im Belegverbund: Rechnung einer Übergabe (MVP-1158), Angebot, AB und Lieferschein (MVP-1161/1164). */
final class EasybillRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        $issued = match (true) {
            $document->is(Quote::class) => [EasybillDocumentIssuer::EXT_TYPE_OFFER, DocumentKind::Quote],
            $document->is(OrderConfirmation::class) => [EasybillDocumentIssuer::EXT_TYPE_ORDER, DocumentKind::OrderConfirmation],
            $document->is(DeliveryNote::class) => [EasybillDocumentIssuer::EXT_TYPE_DELIVERY, DocumentKind::DeliveryNote],
            default => null,
        };
        if ($issued !== null) {
            $counterpart = ExternalReference::query()
                ->forPlugin($organizationId, EasybillPlugin::ID, $issued[0])
                ->where('referenceable_type', $document->type)->where('referenceable_id', $document->id)
                ->first();

            return $counterpart instanceof ExternalReference ? [new DocumentRelation(new DocumentNode(
                ref: DocumentRef::external(EasybillPlugin::ID, AccountingVoucherNode::KIND, (string) $counterpart->external_id),
                label: $issued[1]->label(),
                number: $counterpart->external_number,
                date: $counterpart->synced_at,
                system: 'easybill',
            ), DocumentRelationKind::Counterpart)] : [];
        }
        if (! $document->is(BillingTransfer::class)) {
            return [];
        }
        $reference = ExternalReference::query()
            ->forPlugin($organizationId, EasybillPlugin::ID, EasybillTarget::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $document->type)->where('referenceable_id', $document->id)
            ->first();
        if (! $reference instanceof ExternalReference) {
            return [];
        }

        $voucher = AccountingVoucher::query()->where('organization_id', $organizationId)
            ->where('plugin_id', EasybillPlugin::ID)->where('external_id', $reference->external_id)->first();
        if ($voucher instanceof AccountingVoucher) {
            return [new DocumentRelation(AccountingVoucherNode::of($voucher), DocumentRelationKind::Counterpart)];
        }

        $remote = (array) ($reference->payload['document'] ?? []);
        $number = $reference->external_number ?? ($remote['number'] ?? null);

        return [new DocumentRelation(new DocumentNode(
            ref: DocumentRef::external(EasybillPlugin::ID, AccountingVoucherNode::KIND, (string) $reference->external_id),
            label: DocumentKind::Invoice->label(),
            number: is_scalar($number) && (string) $number !== '' ? (string) $number : null,
            date: $reference->synced_at,
            state: ($remote['is_draft'] ?? true) ? (string) __('easybill::finance.relations.draft') : (string) __('easybill::finance.relations.done'),
            system: 'easybill',
        ), DocumentRelationKind::Counterpart)];
    }
}
