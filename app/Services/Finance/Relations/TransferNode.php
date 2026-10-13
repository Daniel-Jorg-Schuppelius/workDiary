<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferNode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Relations;

use App\Models\Finance\BillingTransfer;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef};
use Illuminate\Support\Facades\Gate;

/** Übergabe (Zeiten oder Material an das Buchhaltungsprogramm) als Knoten im Belegverbund (MVP-1157). */
final class TransferNode {
    public static function of(BillingTransfer $transfer): DocumentNode {
        return new DocumentNode(
            ref: DocumentRef::forModel($transfer),
            label: (string) __('billing.relations.transfer', ['channel' => $transfer->channel->label()]),
            number: $transfer->target->label(),
            date: $transfer->transferred_at ?? $transfer->created_at,
            state: $transfer->status->label(),
            url: Gate::allows('view', $transfer) ? route('finance.transfers.show', $transfer) : null,
        );
    }
}
