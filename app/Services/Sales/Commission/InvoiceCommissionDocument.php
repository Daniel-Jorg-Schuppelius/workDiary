<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceCommissionDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\Invoice;
use App\Services\Invoicing\DunningService;
use App\Services\Sales\Contracts\CommissionDocument;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Support\Carbon;

/** Die lokale Rechnung als provisionsfähiger Beleg (MVP-1119). */
final class InvoiceCommissionDocument implements CommissionDocument {
    public function __construct(
        public readonly Invoice $invoice,
        private readonly DunningService $payments,
    ) {}

    public function documentType(): string {
        return $this->invoice->getMorphClass();
    }

    public function documentId(): int {
        return (int) $this->invoice->getKey();
    }

    public function invoiceId(): int {
        return (int) $this->invoice->getKey();
    }

    public function organizationId(): int {
        return (int) $this->invoice->organization_id;
    }

    public function customerId(): int {
        return (int) $this->invoice->customer_id;
    }

    public function number(): string {
        return (string) ($this->invoice->number ?? $this->invoice->sqid);
    }

    public function currency(): CurrencyCode {
        return $this->invoice->currency;
    }

    public function status(): InvoiceStatus {
        return $this->invoice->status;
    }

    public function netAmount(): Money {
        return $this->invoice->subtotal ?? Money::zero($this->invoice->currency);
    }

    public function netOfCategory(string $category): ?Money {
        $this->invoice->loadMissing('items.article:id,category');

        $amounts = [];
        foreach ($this->invoice->items as $item) {
            // Waehrung der Position kommt vom Beleg (MoneyCast ':invoice.currency').
            $item->setRelation('invoice', $this->invoice);
            if ($item->article_id !== null && (string) $item->article?->category === $category) {
                $amounts[] = $item->amount ?? Money::zero($this->invoice->currency);
            }
        }

        return $amounts === [] ? null : Money::sum($amounts, $this->invoice->currency);
    }

    public function paidShare(): string {
        $total = $this->invoice->total;
        if ($total === null || ! $total->isPositive()) {
            return '0';
        }
        $paid = $this->payments->paidAmount($this->invoice);

        return $paid->greaterThanOrEqual($total) ? '1' : Decimal::of($paid->getAmount())->dividedBy(Decimal::of($total->getAmount()), 10)->getValue();
    }

    public function paidOn(): ?Carbon {
        return $this->invoice->paid_on instanceof Carbon ? $this->invoice->paid_on->copy() : null;
    }

    public function earnedOn(): Carbon {
        $date = $this->invoice->paid_on ?? $this->invoice->issued_on;

        return $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::today();
    }

    public function cancelledOn(): ?Carbon {
        return $this->invoice->cancelled_at instanceof Carbon ? $this->invoice->cancelled_at->copy()->startOfDay() : null;
    }

    public function isReducing(): bool {
        return in_array($this->invoice->type, [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION], true);
    }

    public function origin(): ?CommissionDocument {
        $parent = $this->invoice->parent;

        return $parent instanceof Invoice ? new self($parent, $this->payments) : null;
    }

    public function salesUserId(): ?int {
        return $this->invoice->sales_user_id !== null ? (int) $this->invoice->sales_user_id : null;
    }

    public function salesAgentId(): ?int {
        return $this->invoice->sales_agent_id !== null ? (int) $this->invoice->sales_agent_id : null;
    }

    public function assignSales(?int $userId, ?int $agentId): void {
        $this->invoice->sales_user_id = $userId;
        $this->invoice->sales_agent_id = $agentId;
        $this->invoice->save();
    }
}
