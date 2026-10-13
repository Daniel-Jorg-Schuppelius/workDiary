<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Billing\{DocumentKind, DocumentRelationKind};
use App\Enums\User\Permission;
use App\Models\Billing\{DeliveryNote, DeliveryNoteItem};
use App\Models\Integration\ExternalReference;
use App\Models\Inventory\StockDelivery;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};
use App\Support\MorphMap;
use Illuminate\Support\Facades\{Gate, Lang};

/**
 * Lexoffice im Belegverbund (MVP-1157/1158): das Gegenstück eines lokalen
 * Belegs (übertragene Rechnung, Angebot, AB und Lieferschein aus der
 * Fertigung, Übergabe) und die Bezüge, die Lexoffice selbst meldet
 * (`relatedVouchers`, etwa der in Lexoffice angelegte Abschlag).
 */
final class LexofficeRelationSource implements DocumentRelationSource {
    public const KIND = 'voucher';

    /** Referenztypen eines lokalen Belegs und die Belegart, die er in Lexoffice hat. */
    private const COUNTERPARTS = [
        LexofficeInvoiceService::EXT_TYPE_INVOICE => DocumentKind::Invoice,
        LexofficeQuotationService::EXT_TYPE_QUOTATION => DocumentKind::Quote,
        LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION => DocumentKind::OrderConfirmation,
        LexofficeDeliveryNoteService::EXT_TYPE_DELIVERY_NOTE => DocumentKind::DeliveryNote,
    ];

    public function relationsOf(DocumentRef $document, int $organizationId): array {
        if ($document->isExternal(LexofficePlugin::ID, self::KIND)) {
            return $this->relatedVouchers($document->id, $organizationId);
        }
        if (str_contains($document->type, '.')) {
            return [];
        }

        // Ein Lieferschein aus der Fertigung kann über seine Auslieferung übertragen worden sein (Feature 045).
        $deliveryIds = $document->is(DeliveryNote::class)
            ? DeliveryNoteItem::query()->where('organization_id', $organizationId)->where('delivery_note_id', $document->id)->whereNotNull('stock_delivery_id')->pluck('stock_delivery_id')
            : collect();
        $references = ExternalReference::query()
            ->forPlugin($organizationId, LexofficePlugin::ID)
            ->whereIn('external_type', array_keys(self::COUNTERPARTS))
            ->where(fn ($query) => $query
                ->where(fn ($own) => $own->where('referenceable_type', $document->type)->where('referenceable_id', $document->id))
                ->when($deliveryIds->isNotEmpty(), fn ($query) => $query->orWhere(fn ($legacy) => $legacy
                    ->where('referenceable_type', MorphMap::alias(StockDelivery::class))
                    ->whereIn('referenceable_id', $deliveryIds))))
            ->get();
        if ($references->isEmpty()) {
            return [];
        }
        $vouchers = LexofficeVoucher::query()->where('organization_id', $organizationId)
            ->whereIn('external_id', $references->pluck('external_id'))->get()->keyBy('external_id');

        $relations = [];
        foreach ($references as $reference) {
            $voucher = $vouchers->get($reference->external_id);
            $node = $voucher instanceof LexofficeVoucher ? $this->voucherNode($voucher) : $this->referenceNode($reference);
            $relations[] = new DocumentRelation($node, DocumentRelationKind::Counterpart);
        }

        return $relations;
    }

    /** @return list<DocumentRelation> */
    private function relatedVouchers(string $externalId, int $organizationId): array {
        $voucher = LexofficeVoucher::query()->where('organization_id', $organizationId)->where('external_id', $externalId)->first();
        $ids = $voucher instanceof LexofficeVoucher ? array_map('strval', (array) ($voucher->related_voucher_ids ?? [])) : [];

        // Lexoffice meldet Bezüge nur an Rechnung und Gutschrift; der Abschlag findet seine Schlussrechnung über deren Liste.
        $related = LexofficeVoucher::query()->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereIn('external_id', $ids)->orWhereJsonContains('related_voucher_ids', $externalId))
            ->where('external_id', '!=', $externalId)
            ->get();

        $relations = [];
        foreach ($related as $other) {
            $relations[] = new DocumentRelation($this->voucherNode($other), DocumentRelationKind::Related);
        }

        return $relations;
    }

    private function voucherNode(LexofficeVoucher $voucher): DocumentNode {
        $status = (string) $voucher->voucher_status;

        return new DocumentNode(
            ref: DocumentRef::external(LexofficePlugin::ID, self::KIND, (string) $voucher->external_id),
            label: VoucherTypes::classify($voucher->voucher_type)->kind->label(),
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            state: $status !== '' && Lang::has('values.' . $status) ? (string) __('values.' . $status) : ($status !== '' ? $status : null),
            system: 'Lexoffice',
            url: Gate::allows(Permission::VoucherViewAny->value) ? route('lexoffice.vouchers.preview', $voucher) : null,
            dialog: true,
        );
    }

    /** Noch nicht gespiegelt (Abgleich steht aus): Angaben aus der Referenz. */
    private function referenceNode(ExternalReference $reference): DocumentNode {
        $kind = ($reference->payload['_resource'] ?? null) === LexofficeInvoiceService::RESOURCE_CREDIT_NOTES
            ? DocumentKind::CreditNote
            : self::COUNTERPARTS[$reference->external_type];
        $number = $reference->external_number ?? ($reference->payload['voucherNumber'] ?? null);

        return new DocumentNode(
            ref: DocumentRef::external(LexofficePlugin::ID, self::KIND, (string) $reference->external_id),
            label: $kind->label(),
            number: is_scalar($number) && (string) $number !== '' ? (string) $number : null,
            date: $reference->synced_at,
            state: (string) __('lexoffice::billing.relations.not_mirrored'),
            system: 'Lexoffice',
        );
    }
}
