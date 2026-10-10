<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherRevenueSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Finance\AccountingVoucher;
use App\Models\Invoicing\Invoice;
use App\Services\Billing\Contracts\ExternalRevenueSource;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Builder;

/**
 * Umsatzbelege aus dem allgemeinen Belegspiegel `accounting_vouchers`
 * (MVP-1127): sevDesk, easybill, JTL-Wawi, InvoicePlane und jedes künftige
 * Programm, das über {@see VoucherMirror} spiegelt. Herkunft je Beleg ist die
 * Plugin-ID. Regeln wie der Belegfluss ({@see \App\Services\Billing\Feed\Sources\AccountingVoucherSource}):
 * ausgehend, Gutschriften mindern; Entwürfe und stornierte Belege zählen
 * nicht, Stornodokumente auch nicht (ihr Ursprung steht schon auf storniert).
 * Positionen und Belegdateien führt der Spiegel nicht.
 */
final class AccountingVoucherRevenueSource implements ExternalRevenueSource {
    public const KEY = 'accounting_vouchers';

    /** sevDesk-Status vor MVP-731 (normalisierter Zustand fehlt in Altzeilen). */
    private const LEGACY_STATES = ['50' => 'draft', '100' => 'open', '750' => 'open', '1000' => 'paid'];

    public function key(): string {
        return self::KEY;
    }

    public function invoices(ExternalInvoiceQuery $query): array {
        $vouchers = $this->revenueVouchers($query->withCreditNotes)
            ->when($query->organizationId !== null, static fn (Builder $q) => $q->where('accounting_vouchers.organization_id', $query->organizationId))
            ->when($query->issuedFrom, static fn (Builder $q, CarbonInterface $from) => $q->where('accounting_vouchers.voucher_date', '>=', DateRange::day($from)))
            ->when($query->issuedTo, static fn (Builder $q, CarbonInterface $to) => $q->where('accounting_vouchers.voucher_date', '<', DateRange::dayAfter($to)))
            ->when($query->customerId !== null, static fn (Builder $q) => $q->where('accounting_vouchers.customer_id', $query->customerId))
            ->when($query->excludedCustomerIds !== [], static fn (Builder $q) => $q->where(static fn (Builder $w) => $w
                ->whereNull('accounting_vouchers.customer_id')
                ->orWhereNotIn('accounting_vouchers.customer_id', $query->excludedCustomerIds)))
            ->orderBy('voucher_date')
            ->orderBy('id')
            ->get(['id', 'plugin_id', 'customer_id', 'voucher_status', 'voucher_state', 'document_kind', 'voucher_number', 'voucher_date', 'due_date', 'paid_date', 'total_amount', 'net_amount', 'open_amount', 'currency']);

        $invoices = [];
        foreach ($vouchers as $voucher) {
            $state = self::state($voucher);
            if ($query->openOnly && $state !== AccountingVoucherState::Open) {
                continue;
            }
            $kind = DocumentKind::tryFrom((string) $voucher->document_kind) ?? DocumentKind::Other;
            $sign = $kind->sign() < 0 ? -1.0 : 1.0;
            $gross = round($sign * abs((float) $voucher->total_amount), 2);
            $open = $state === AccountingVoucherState::Paid ? 0.0 : round($sign * abs((float) ($voucher->open_amount ?? $voucher->total_amount)), 2);

            $invoices[] = new ExternalInvoice(
                source: (string) $voucher->plugin_id,
                key: (string) $voucher->id,
                number: (string) $voucher->voucher_number,
                customerId: $voucher->customer_id !== null ? (int) $voucher->customer_id : null,
                contactName: null,
                type: match ($kind) {
                    DocumentKind::DownPayment => Invoice::TYPE_DOWN_PAYMENT,
                    DocumentKind::CreditNote => Invoice::TYPE_CREDIT_NOTE,
                    default => Invoice::TYPE_INVOICE,
                },
                status: match (true) {
                    $state === AccountingVoucherState::Paid => InvoiceStatus::Paid,
                    abs($open) > 0 && abs($open) < abs($gross) => InvoiceStatus::PartiallyPaid,
                    default => InvoiceStatus::Issued,
                },
                issuedOn: $voucher->voucher_date !== null ? CarbonImmutable::parse($voucher->voucher_date->toDateString()) : null,
                dueOn: $voucher->due_date !== null ? CarbonImmutable::parse($voucher->due_date->toDateString()) : null,
                paidOn: $voucher->paid_date !== null ? CarbonImmutable::parse($voucher->paid_date->toDateString()) : null,
                gross: $gross,
                net: $voucher->net_amount !== null ? round($sign * abs((float) $voucher->net_amount), 2) : null,
                open: $open,
                currency: (string) ($voucher->currency ?: 'EUR'),
                url: null,
            );
        }

        return $invoices;
    }

    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array {
        return [];
    }

    public function customerIdsWithDocuments(int $organizationId): array {
        return array_values(AccountingVoucher::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }

    public function file(int $organizationId, string $source, string $key): ?ExternalInvoiceFile {
        return null;
    }

    public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile {
        return null;
    }

    /** Normalisierter Zustand, für Altzeilen aus dem sevDesk-Status. */
    public static function state(AccountingVoucher $voucher): AccountingVoucherState {
        if ($voucher->voucher_state instanceof AccountingVoucherState) {
            return $voucher->voucher_state;
        }

        return AccountingVoucherState::from(self::LEGACY_STATES[(string) $voucher->voucher_status] ?? 'open');
    }

    /**
     * Ausgehende Umsatzbelege — die eine Regel dieser Quelle. `$withCancelled`
     * nimmt stornierte hinzu (Provisionen müssen den Storno sehen, MVP-1128).
     *
     * @return Builder<AccountingVoucher>
     */
    public function revenueVouchers(bool $withCreditNotes = true, bool $withCancelled = false): Builder {
        $kinds = [DocumentKind::Invoice->value, DocumentKind::DownPayment->value, DocumentKind::Other->value];
        if ($withCreditNotes) {
            $kinds[] = DocumentKind::CreditNote->value;
        }
        $states = [AccountingVoucherState::Open->value, AccountingVoucherState::Paid->value];
        if ($withCancelled) {
            $states[] = AccountingVoucherState::Cancelled->value;
        }

        return AccountingVoucher::query()
            ->whereNotNull('accounting_vouchers.voucher_date')
            ->where(static fn (Builder $q) => $q
                ->where('accounting_vouchers.direction', DocumentDirection::Outgoing->value)
                ->orWhere(static fn (Builder $w) => $w->whereNull('accounting_vouchers.direction')->where('accounting_vouchers.voucher_type', 'C')))
            ->where(static fn (Builder $q) => $q->whereNull('accounting_vouchers.document_kind')->orWhereIn('accounting_vouchers.document_kind', $kinds))
            ->where(static fn (Builder $q) => $q
                ->whereIn('accounting_vouchers.voucher_state', $states)
                ->orWhere(static fn (Builder $w) => $w->whereNull('accounting_vouchers.voucher_state')->where(static fn (Builder $x) => $x
                    ->whereNull('accounting_vouchers.voucher_status')
                    ->orWhere('accounting_vouchers.voucher_status', '!=', '50'))))
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('external_references')
                    ->whereColumn('external_references.plugin_id', 'accounting_vouchers.plugin_id')
                    ->whereColumn('external_references.external_id', 'accounting_vouchers.external_id')
                    ->where('external_references.referenceable_type', (new Invoice)->getMorphClass());
            })
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.organization_id', 'accounting_vouchers.organization_id')
                    ->where(static fn ($w) => $w
                        ->whereColumn('invoices.number', 'accounting_vouchers.voucher_number')
                        ->orWhereColumn('invoices.external_number', 'accounting_vouchers.voucher_number'));
            });
    }
}
