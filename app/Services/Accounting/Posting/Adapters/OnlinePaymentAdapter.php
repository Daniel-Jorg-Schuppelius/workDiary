<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind, SettlementKind};
use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\Organization;
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Online-Zahlung einer Rechnung (MVP-1067): Geldtransit an Forderung, die
 * Gebühr des Anbieters als Nebenkosten des Geldverkehrs gegen Geldtransit,
 * Erstattungen zurück. Die spätere Auszahlung des Anbieters ist ein Bankumsatz
 * gegen das Geldtransit-Konto (Buchungseingang, Verrechnungskonto).
 */
class OnlinePaymentAdapter extends AbstractPostingAdapter {
    public function kind(): PostingSourceKind {
        return PostingSourceKind::OnlinePayment;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        $query = OnlinePayment::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [OnlinePaymentStatus::Paid->value, OnlinePaymentStatus::Refunded->value]);
        DateRange::whereTimestampBetween($query, 'paid_at', $from, $to);
        /** @var Collection<int, Model> $payments */
        $payments = $query->with('invoice')->orderBy('paid_at')->get();

        return $payments;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof OnlinePayment);

        $bookedOn = CarbonImmutable::parse($source->paid_at ?? now())->startOfDay();
        $invoice = $source->invoice;
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        $foreign = $this->foreignCurrencyBlocker($organization, $source->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }
        if (! $invoice instanceof Invoice) {
            $blockers[] = (string) __('accounting.inbox.blocker.unsupported_target');
        }

        $gross = $source->gross_amount->getAmount();
        $fee = $source->fee_amount?->getAmount() ?? '0.00';
        $refunded = $source->refunded_amount->getAmount();
        $memo = (string) __('accounting.inbox.memo.online_payment', [
            'provider' => $source->provider,
            'invoice' => $invoice instanceof Invoice ? (string) $invoice->number : '—',
        ]);

        $transit = $this->rule($organization, PostingAccountRole::PaymentTransit, [], $bookedOn);
        $receivable = $this->rule($organization, PostingAccountRole::Receivable, [], $bookedOn);
        if ($transit === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::PaymentTransit);
        }
        if ($receivable === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Receivable);
        }

        $customer = $invoice instanceof Invoice ? $invoice->customer_id : null;
        $pairs = [];
        if ($transit !== null && $receivable !== null) {
            // Geldtransit an Forderung; eine Erstattung dreht den erstatteten Teil zurück.
            $pairs[] = [PostingAccountRole::PaymentTransit, $transit, $gross, '0.00', null];
            $pairs[] = [PostingAccountRole::Receivable, $receivable, '0.00', $gross, $customer];
            if (! NumberHelper::isZeroPrecise($refunded)) {
                $pairs[] = [PostingAccountRole::Receivable, $receivable, $refunded, '0.00', $customer];
                $pairs[] = [PostingAccountRole::PaymentTransit, $transit, '0.00', $refunded, null];
            }
        }
        if (! NumberHelper::isZeroPrecise($fee)) {
            $fees = $this->rule($organization, PostingAccountRole::PaymentFees, [], $bookedOn);
            if ($fees === null || $transit === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::PaymentFees);
            } else {
                $pairs[] = [PostingAccountRole::PaymentFees, $fees, $fee, '0.00', null];
                $pairs[] = [PostingAccountRole::PaymentTransit, $transit, '0.00', $fee, null];
            }
        }

        foreach ($pairs as [$role, $rule, $debit, $credit, $customerId]) {
            $line = $this->line($role, $rule, $debit, $credit, $memo, $customerId !== null ? Customer::class : null, $customerId);
            if ($line instanceof PostingProposalLine) {
                $lines[] = $line;
                $ruleVersions[] = $rule->versionTag();
            }
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $bookedOn,
            memo: $memo,
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $bookedOn,
            documentReference: $invoice instanceof Invoice ? (string) $invoice->number : null,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: $memo,
            extra: $invoice instanceof Invoice ? [
                'settles_source_type' => $invoice->getMorphClass(),
                'settles_source_id' => $invoice->getKey(),
                'settlement_kind' => SettlementKind::Payment->value,
            ] : [],
        );
    }
}
