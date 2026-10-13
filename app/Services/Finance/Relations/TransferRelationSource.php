<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Relations;

use App\Enums\Billing\DocumentRelationKind;
use App\Models\Finance\BillingTransfer;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentRef, DocumentRelation};

/** Korrekturübergaben im Belegverbund (MVP-1157). */
final class TransferRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        if (! $document->is(BillingTransfer::class)) {
            return [];
        }
        $transfer = BillingTransfer::query()->where('organization_id', $organizationId)->find($document->id);
        if (! $transfer instanceof BillingTransfer) {
            return [];
        }

        $relations = [];
        $corrected = $transfer->corrects_transfer_id !== null
            ? BillingTransfer::query()->where('organization_id', $organizationId)->find($transfer->corrects_transfer_id)
            : null;
        if ($corrected instanceof BillingTransfer) {
            $relations[] = new DocumentRelation(TransferNode::of($corrected), DocumentRelationKind::Corrects);
        }
        foreach (BillingTransfer::query()->where('organization_id', $organizationId)->where('corrects_transfer_id', $transfer->id)->get() as $correction) {
            $relations[] = new DocumentRelation(TransferNode::of($correction), DocumentRelationKind::CorrectedBy);
        }

        return $relations;
    }
}
