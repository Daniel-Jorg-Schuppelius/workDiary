<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Relations;

use App\Enums\Billing\DocumentRelationKind;
use App\Models\Finance\AccountingVoucher;
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentRef, DocumentRelation};

/** Storno ↔ Original im allgemeinen Belegspiegel (easybill, InvoicePlane, JTL; MVP-1158). */
final class AccountingVoucherRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        [$pluginId, $kind] = array_pad(explode('.', $document->type, 2), 2, '');
        if ($kind !== AccountingVoucherNode::KIND) {
            return [];
        }
        $voucher = AccountingVoucher::query()->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)->where('external_id', $document->id)->first();
        if (! $voucher instanceof AccountingVoucher) {
            return [];
        }

        $relations = [];
        $original = $voucher->cancels_external_id !== null
            ? AccountingVoucher::query()->where('organization_id', $organizationId)->where('plugin_id', $pluginId)->where('external_id', $voucher->cancels_external_id)->first()
            : null;
        if ($original instanceof AccountingVoucher) {
            $relations[] = new DocumentRelation(AccountingVoucherNode::of($original), DocumentRelationKind::Corrects);
        }
        $cancellations = AccountingVoucher::query()->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)->where('cancels_external_id', $voucher->external_id)->get();
        foreach ($cancellations as $cancellation) {
            $relations[] = new DocumentRelation(AccountingVoucherNode::of($cancellation), DocumentRelationKind::CorrectedBy);
        }

        return $relations;
    }
}
