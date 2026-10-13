<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Enums\Billing\{DocumentKind, DocumentRelationKind};
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};

/**
 * Die sevDesk-Rechnung einer Übergabe im Belegverbund (MVP-1158). sevDesk-
 * Rechnungen werden nicht gespiegelt; Nummer und Status holt der Belegabruf
 * an die Referenz ({@see SevDeskInvoiceNumberPull}).
 */
final class SevDeskRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        if (! $document->is(BillingTransfer::class)) {
            return [];
        }
        $reference = ExternalReference::query()
            ->forPlugin($organizationId, SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $document->type)->where('referenceable_id', $document->id)
            ->first();
        if (! $reference instanceof ExternalReference) {
            return [];
        }

        $remote = (array) ($reference->payload['invoice'] ?? []);
        $number = $reference->external_number ?? ($remote['invoiceNumber'] ?? null);
        $status = (string) ($reference->payload['status'] ?? $remote['status'] ?? '');

        return [new DocumentRelation(new DocumentNode(
            ref: DocumentRef::external(SevDeskPlugin::ID, 'invoice', (string) $reference->external_id),
            label: DocumentKind::Invoice->label(),
            number: is_scalar($number) && (string) $number !== '' ? (string) $number : null,
            date: $reference->synced_at,
            state: $status !== '' ? (string) __('sevdesk::finance.invoice_status.' . $status) : null,
            system: 'sevDesk',
        ), DocumentRelationKind::Counterpart)];
    }
}
