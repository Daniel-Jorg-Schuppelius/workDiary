<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherNode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Relations;

use App\Enums\Billing\DocumentKind;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\PluginManager;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef};
use Illuminate\Support\Facades\Lang;

/** Beleg aus dem allgemeinen Belegspiegel (Phase 139) als Knoten im Belegverbund (MVP-1158). */
final class AccountingVoucherNode {
    public const KIND = 'voucher';

    public static function of(AccountingVoucher $voucher): DocumentNode {
        $state = $voucher->voucher_state?->value;

        return new DocumentNode(
            ref: DocumentRef::external((string) $voucher->plugin_id, self::KIND, (string) $voucher->external_id),
            label: (DocumentKind::tryFrom((string) $voucher->document_kind) ?? DocumentKind::Other)->label(),
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            state: $state !== null && Lang::has('values.' . $state) ? (string) __('values.' . $state) : $state,
            system: app(PluginManager::class)->find((string) $voucher->plugin_id)?->name() ?? (string) $voucher->plugin_id,
        );
    }
}
