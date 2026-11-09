<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherPurchaseSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\DocumentKind;
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Services\Billing\Contracts\ExternalPurchaseSource;
use App\Services\Billing\Dto\ExternalPurchase;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;

/**
 * Einkaufsbelege aus dem allgemeinen Belegspiegel `accounting_vouchers`
 * (MVP-1129) für Lieferantenwert, Lieferantenanalyse und die Kachel „Offene
 * Posten“ — anbieterneutral wie {@see AccountingVoucherRevenueSource}.
 * Buchungskategorien führt der Spiegel nicht.
 */
final class AccountingVoucherPurchaseSource implements ExternalPurchaseSource {
    public function key(): string {
        return AccountingVoucherRevenueSource::KEY;
    }

    public function purchases(?CarbonInterface $from, ?CarbonInterface $to, ?array $supplierIds = null): array {
        $query = AccountingVoucher::query()->purchases()
            ->whereNotNull('accounting_vouchers.supplier_id')
            ->where('accounting_vouchers.archived', false)
            ->when($supplierIds !== null, static fn (Builder $q) => $q->whereIn('accounting_vouchers.supplier_id', $supplierIds ?? []));
        if ($from !== null && $to !== null) {
            $query->whereBetween('accounting_vouchers.voucher_date', DateRange::days($from, $to));
        }

        $purchases = [];
        foreach ($query->get(['supplier_id', 'voucher_status', 'voucher_state', 'document_kind', 'voucher_date', 'total_amount', 'open_amount']) as $voucher) {
            if ($voucher->voucher_date === null) {
                continue;
            }
            $kind = DocumentKind::tryFrom((string) $voucher->document_kind) ?? DocumentKind::Other;
            $purchases[] = new ExternalPurchase(
                supplierId: (int) $voucher->supplier_id,
                date: CarbonImmutable::parse($voucher->voucher_date->toDateString()),
                amount: ($kind->sign() < 0 ? -1.0 : 1.0) * abs((float) $voucher->total_amount),
                open: AccountingVoucherRevenueSource::state($voucher) === AccountingVoucherState::Paid ? 0.0 : abs((float) ($voucher->open_amount ?? 0.0)),
            );
        }

        return $purchases;
    }

    public function categorySpend(CarbonInterface $from, CarbonInterface $to): array {
        return ['rows' => [], 'pending' => 0];
    }

    public function openPayables(int $organizationId, string $today): array {
        $result = ['open' => 0.0, 'overdue' => 0.0, 'count' => 0];
        $vouchers = AccountingVoucher::query()->purchases()
            ->where('accounting_vouchers.organization_id', $organizationId)
            ->where(static fn (Builder $q) => $q->whereNull('accounting_vouchers.document_kind')->orWhere('accounting_vouchers.document_kind', '!=', DocumentKind::CreditNote->value))
            ->get(['voucher_status', 'voucher_state', 'due_date', 'total_amount', 'open_amount']);
        foreach ($vouchers as $voucher) {
            if (AccountingVoucherRevenueSource::state($voucher) !== AccountingVoucherState::Open) {
                continue;
            }
            $open = abs((float) ($voucher->open_amount ?? $voucher->total_amount));
            if ($open <= 0) {
                continue;
            }
            $result['open'] += $open;
            $result['count']++;
            if ($voucher->due_date !== null && $voucher->due_date->toDateString() < $today) {
                $result['overdue'] += $open;
            }
        }

        return $result;
    }
}
