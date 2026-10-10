<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherCommissionDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\DocumentKind;
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Finance\AccountingVoucher;
use App\Services\Sales\Contracts\CommissionDocument;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Support\Carbon;

/**
 * Gespiegelte Rechnung des allgemeinen Belegspiegels als provisionsfähiger
 * Beleg (MVP-1128). Grundlage ist das gespiegelte Netto; ohne Netto keine
 * Provision. Positionen und den Bezug einer Gutschrift führt der Spiegel nicht.
 */
final class AccountingVoucherCommissionDocument implements CommissionDocument {
    public function __construct(public readonly AccountingVoucher $voucher) {}

    public function documentType(): string {
        return $this->voucher->getMorphClass();
    }

    public function documentId(): int {
        return (int) $this->voucher->getKey();
    }

    public function invoiceId(): ?int {
        return null;
    }

    public function organizationId(): int {
        return (int) $this->voucher->organization_id;
    }

    public function customerId(): ?int {
        return $this->voucher->customer_id !== null ? (int) $this->voucher->customer_id : null;
    }

    public function number(): string {
        return (string) ($this->voucher->voucher_number ?: $this->voucher->external_id);
    }

    public function currency(): CurrencyCode {
        return CurrencyCode::tryFrom((string) $this->voucher->currency) ?? CurrencyCode::Euro;
    }

    public function status(): InvoiceStatus {
        $total = abs((float) $this->voucher->total_amount);
        $open = $this->voucher->open_amount !== null ? abs((float) $this->voucher->open_amount) : $total;

        return match (true) {
            AccountingVoucherRevenueSource::state($this->voucher) === AccountingVoucherState::Cancelled => InvoiceStatus::Cancelled,
            AccountingVoucherRevenueSource::state($this->voucher) === AccountingVoucherState::Paid => InvoiceStatus::Paid,
            $open > 0 && $open < $total => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };
    }

    public function netAmount(): Money {
        return $this->voucher->net_amount !== null
            ? Money::of((string) $this->voucher->net_amount, $this->currency())->abs()
            : Money::zero($this->currency());
    }

    public function netOfCategory(string $category): ?Money {
        return null;
    }

    public function paidShare(): string {
        if (AccountingVoucherRevenueSource::state($this->voucher) === AccountingVoucherState::Paid) {
            return '1';
        }
        $total = abs((float) $this->voucher->total_amount);
        if ($total <= 0 || $this->voucher->open_amount === null) {
            return '0';
        }
        $paid = max(0.0, $total - abs((float) $this->voucher->open_amount));

        return $paid >= $total ? '1' : Decimal::of((string) $paid)->dividedBy(Decimal::of((string) $total), 10)->getValue();
    }

    public function paidOn(): ?Carbon {
        return $this->voucher->paid_date?->copy();
    }

    public function earnedOn(): Carbon {
        return $this->voucher->paid_date !== null ? $this->voucher->paid_date->copy()->startOfDay() : Carbon::today();
    }

    public function cancelledOn(): ?Carbon {
        return AccountingVoucherRevenueSource::state($this->voucher) === AccountingVoucherState::Cancelled ? Carbon::today() : null;
    }

    public function isReducing(): bool {
        return $this->voucher->document_kind === DocumentKind::CreditNote->value;
    }

    public function origin(): ?CommissionDocument {
        return null;
    }

    public function salesUserId(): ?int {
        return $this->voucher->sales_user_id;
    }

    public function salesAgentId(): ?int {
        return $this->voucher->sales_agent_id;
    }

    public function assignSales(?int $userId, ?int $agentId): void {
        $this->voucher->forceFill(['sales_user_id' => $userId, 'sales_agent_id' => $agentId])->save();
    }
}
