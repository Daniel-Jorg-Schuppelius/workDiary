<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherCommissionDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Services\Sales\Contracts\{CommissionDocument, CommissionDocumentSource};

/** Rechnungen des allgemeinen Belegspiegels als Provisionsbelege (MVP-1128). */
final class AccountingVoucherCommissionDocuments implements CommissionDocumentSource {
    public function __construct(private readonly AccountingVoucherRevenueSource $revenue) {}

    public function documentType(): string {
        return (new AccountingVoucher)->getMorphClass();
    }

    public function find(int $organizationId, int $id): ?CommissionDocument {
        $voucher = $this->revenue->revenueVouchers(withCancelled: true)
            ->where('accounting_vouchers.organization_id', $organizationId)
            ->whereKey($id)
            ->first();

        return $voucher instanceof AccountingVoucher ? new AccountingVoucherCommissionDocument($voucher) : null;
    }

    public function unassignedPaid(int $organizationId, int $limit): array {
        $type = $this->documentType();

        return array_values($this->revenue->revenueVouchers(withCreditNotes: false)
            ->where('accounting_vouchers.organization_id', $organizationId)
            ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                ->from('invoice_commissions')
                ->where('invoice_commissions.document_type', $type)
                ->whereColumn('invoice_commissions.document_id', 'accounting_vouchers.id'))
            ->where(static fn ($q) => $q
                ->where('accounting_vouchers.voucher_state', AccountingVoucherState::Paid->value)
                ->orWhere(static fn ($w) => $w->whereNull('accounting_vouchers.voucher_state')->where('accounting_vouchers.voucher_status', '1000')))
            ->orderByDesc('accounting_vouchers.paid_date')
            ->orderByDesc('accounting_vouchers.voucher_date')
            ->limit($limit)
            ->get()
            ->map(static fn (AccountingVoucher $voucher): CommissionDocument => new AccountingVoucherCommissionDocument($voucher))
            ->all());
    }
}
