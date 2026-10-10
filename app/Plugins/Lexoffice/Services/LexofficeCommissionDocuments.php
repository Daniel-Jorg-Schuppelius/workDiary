<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeCommissionDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Sales\Contracts\{CommissionDocument, CommissionDocumentSource};

/**
 * Gespiegelte Rechnungen und Gutschriften als Provisionsbelege (MVP-1119).
 * Belegauswahl wie in den Auswertungen ({@see LexofficeRevenueSource}):
 * übergebene lokale Rechnungen tragen ihre Provision lokal.
 */
final class LexofficeCommissionDocuments implements CommissionDocumentSource {
    public function __construct(private readonly LexofficeRevenueSource $revenue) {}

    public function documentType(): string {
        return (new LexofficeVoucher)->getMorphClass();
    }

    public function find(int $organizationId, int $id): ?CommissionDocument {
        $voucher = $this->revenue->revenueVouchers(VoucherTypes::REVENUE_WITH_CREDITS, withVoided: true)
            ->where('lexoffice_vouchers.organization_id', $organizationId)
            ->whereKey($id)
            ->first();

        return $voucher instanceof LexofficeVoucher ? new LexofficeCommissionDocument($voucher) : null;
    }

    public function unassignedPaid(int $organizationId, int $limit): array {
        $type = $this->documentType();

        return array_values($this->revenue->revenueVouchers(VoucherTypes::REVENUE)
            ->where('lexoffice_vouchers.organization_id', $organizationId)
            ->where('lexoffice_vouchers.voucher_status', 'paid')
            ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                ->from('invoice_commissions')
                ->where('invoice_commissions.document_type', $type)
                ->whereColumn('invoice_commissions.document_id', 'lexoffice_vouchers.id'))
            ->orderByDesc('lexoffice_vouchers.paid_date')
            ->orderByDesc('lexoffice_vouchers.voucher_date')
            ->limit($limit)
            ->get()
            ->map(static fn (LexofficeVoucher $voucher): CommissionDocument => new LexofficeCommissionDocument($voucher))
            ->all());
    }
}
