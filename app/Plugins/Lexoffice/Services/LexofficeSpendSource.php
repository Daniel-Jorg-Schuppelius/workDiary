<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSpendSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficePostingCategory, LexofficeVoucher, LexofficeVoucherCategory};
use App\Services\Billing\Contracts\ExternalPurchaseSource;
use App\Services\Billing\Dto\{ExternalCategorySpend, ExternalPurchase};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;

/**
 * Einkaufsbelege aus dem Lexoffice-Beleg-Spiegel ({@see LexofficeVoucher} mit
 * `supplier_id`) für Lieferantenwert und -analyse (MVP-1036). Filter wie
 * zuvor in den Berichten: Ausgabearten, ohne Entwürfe/Stornos, nicht archiviert.
 */
final class LexofficeSpendSource implements ExternalPurchaseSource {
    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function purchases(?CarbonInterface $from, ?CarbonInterface $to, ?array $supplierIds = null): array {
        $purchases = [];
        $this->vouchers($from, $to)
            ->when($supplierIds !== null, fn (Builder $q) => $q->whereIn('supplier_id', $supplierIds ?? []))
            ->get(['supplier_id', 'voucher_type', 'voucher_date', 'total_amount', 'open_amount'])
            ->each(function (LexofficeVoucher $voucher) use (&$purchases): void {
                if ($voucher->voucher_date === null) {
                    return;
                }
                $purchases[] = new ExternalPurchase(
                    supplierId: (int) $voucher->supplier_id,
                    date: CarbonImmutable::parse($voucher->voucher_date->toDateString()),
                    amount: $this->sign($voucher->voucher_type) * ($voucher->total_amount?->toFloat() ?? 0.0),
                    open: $voucher->open_amount?->toFloat() ?? 0.0,
                );
            });

        return $purchases;
    }

    public function categorySpend(CarbonInterface $from, CarbonInterface $to): array {
        $vouchers = $this->vouchers($from, $to);
        $pending = (clone $vouchers)->whereNull('categories_synced_at')->count();

        $names = LexofficePostingCategory::query()->pluck('name', 'external_id')->all();
        $rows = [];
        LexofficeVoucherCategory::query()
            ->with('voucher:id,voucher_type,voucher_date')
            ->whereIn('voucher_id', (clone $vouchers)->select('id'))
            ->get()
            ->each(function (LexofficeVoucherCategory $row) use (&$rows, $names): void {
                $date = $row->voucher->voucher_date;
                if ($date === null) {
                    return;
                }
                $rows[] = new ExternalCategorySpend(
                    category: (string) ($names[(string) $row->category_external_id] ?? __('lexoffice::reporting.supplier_category.unknown')),
                    date: CarbonImmutable::parse($date->toDateString()),
                    amount: $row->net_amount->toFloat() * $this->sign($row->voucher->voucher_type),
                );
            });

        return ['rows' => $rows, 'pending' => $pending];
    }

    /** @return Builder<LexofficeVoucher> */
    private function vouchers(?CarbonInterface $from, ?CarbonInterface $to): Builder {
        $query = LexofficeVoucher::query()
            ->whereNotNull('supplier_id')
            ->where('archived', false)
            ->whereNotNull('voucher_date')
            ->whereIn('voucher_type', VoucherTypes::EXPENSES)
            ->whereNotIn('voucher_status', ['draft', 'voided']);
        if ($from !== null && $to !== null) {
            $query->whereBetween('voucher_date', DateRange::days($from, $to));
        }

        return $query;
    }

    /** Gutschriften mindern die Ausgaben. */
    private function sign(?string $voucherType): float {
        return in_array($voucherType, VoucherTypes::EXPENSE_CREDITS, true) ? -1.0 : 1.0;
    }
}
