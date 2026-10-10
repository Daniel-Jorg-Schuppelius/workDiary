<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeCommissionDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Invoicing\InvoiceStatus;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Sales\Contracts\CommissionDocument;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Support\Carbon;

/**
 * Gespiegelte Lexoffice-Rechnung als provisionsfähiger Beleg (MVP-1119).
 * Netto aus dem Spiegel (Positions-Sync), sonst einmalig per Detailabruf;
 * Produktgruppen über die Artikel-Zuordnung der Positionen.
 */
final class LexofficeCommissionDocument implements CommissionDocument {
    /** `paidoff` = Gutschrift vollständig zurückgezahlt (Lexware-Doku). */
    private const PAID_STATUSES = ['paid', 'paidoff'];

    public function __construct(public readonly LexofficeVoucher $voucher) {}

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
        return $this->voucher->customer_id;
    }

    public function number(): string {
        return (string) ($this->voucher->voucher_number ?: $this->voucher->external_id);
    }

    public function currency(): CurrencyCode {
        return $this->voucher->currency;
    }

    public function status(): InvoiceStatus {
        $total = abs($this->voucher->total_amount?->toFloat() ?? 0.0);
        $open = $this->voucher->open_amount !== null ? abs($this->voucher->open_amount->toFloat()) : $total;

        return match (true) {
            $this->voucher->voucher_status === 'voided' => InvoiceStatus::Cancelled,
            in_array($this->voucher->voucher_status, self::PAID_STATUSES, true) => InvoiceStatus::Paid,
            $open > 0 && $open < $total => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };
    }

    public function netAmount(): Money {
        if ($this->voucher->net_amount !== null) {
            return $this->voucher->net_amount->abs();
        }
        $lines = $this->voucher->lines()->get(['total_net', 'currency']);
        if ($lines->isNotEmpty()) {
            return Money::sum($lines->map(static fn (LexofficeVoucherLine $line): Money => $line->total_net->abs())->all(), $this->voucher->currency);
        }

        return app(LexofficeVoucherNetAmount::class)->for($this->voucher)?->abs() ?? Money::zero($this->voucher->currency);
    }

    public function netOfCategory(string $category): ?Money {
        $lines = LexofficeVoucherLine::query()
            ->join('external_article_mappings', function ($join): void {
                $join->on('external_article_mappings.external_id', '=', 'lexoffice_voucher_lines.external_article_id')
                    ->on('external_article_mappings.organization_id', '=', 'lexoffice_voucher_lines.organization_id')
                    ->where('external_article_mappings.plugin_id', '=', LexofficePlugin::ID);
            })
            ->join('articles', 'articles.id', '=', 'external_article_mappings.article_id')
            ->where('lexoffice_voucher_lines.voucher_id', $this->voucher->id)
            ->where('articles.category', $category)
            ->get(['lexoffice_voucher_lines.total_net', 'lexoffice_voucher_lines.currency']);

        return $lines->isEmpty()
            ? null
            : Money::sum($lines->map(static fn (LexofficeVoucherLine $line): Money => $line->total_net->abs())->all(), $this->voucher->currency);
    }

    public function paidShare(): string {
        if (in_array($this->voucher->voucher_status, self::PAID_STATUSES, true)) {
            return '1';
        }
        $total = $this->voucher->total_amount?->abs();
        if ($total === null || ! $total->isPositive() || $this->voucher->open_amount === null) {
            return '0';
        }
        $paid = Money::max($total->minus($this->voucher->open_amount->abs()), Money::zero($total->getCurrency()));

        return $paid->greaterThanOrEqual($total) ? '1' : Decimal::of($paid->getAmount())->dividedBy(Decimal::of($total->getAmount()), 10)->getValue();
    }

    public function paidOn(): ?Carbon {
        return $this->voucher->paid_date?->copy();
    }

    public function earnedOn(): Carbon {
        // Ohne Zahldatum aus der Payments-Anreicherung zählt der Tag, an dem
        // der Sync den Wechsel sieht — nie das alte Belegdatum (geschlossene Perioden).
        return $this->voucher->paid_date !== null ? $this->voucher->paid_date->copy()->startOfDay() : Carbon::today();
    }

    public function cancelledOn(): ?Carbon {
        return $this->voucher->voucher_status === 'voided' ? Carbon::today() : null;
    }

    public function isReducing(): bool {
        return in_array($this->voucher->voucher_type, ['creditnote', 'salescreditnote'], true);
    }

    public function origin(): ?CommissionDocument {
        $related = $this->voucher->related_voucher_ids ?? [];
        if ($related === []) {
            return null;
        }
        $origin = LexofficeVoucher::query()
            ->where('organization_id', $this->voucher->organization_id)
            ->whereIn('external_id', $related)
            ->whereIn('voucher_type', VoucherTypes::REVENUE)
            ->first();

        return $origin instanceof LexofficeVoucher ? new self($origin) : null;
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
