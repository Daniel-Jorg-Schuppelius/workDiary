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
use App\Models\Accounting\{AccountingEntry, AccountingPostingRule};
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\Organization;
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine, PostingRuleResolver, PrepaymentAdvance};
use App\Support\MorphMap;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Online-Zahlung einer Rechnung (MVP-1067): Geldtransit an Forderung, die
 * Gebühr des Anbieters als Nebenkosten des Geldverkehrs gegen Geldtransit,
 * Erstattungen zurück. Die spätere Auszahlung des Anbieters ist ein Bankumsatz
 * gegen das Geldtransit-Konto (Buchungseingang, Verrechnungskonto). Eine
 * Vorauszahlung bucht am Zahlungstag als erhaltene Anzahlung mit
 * Umsatzsteuer (MVP-1523, MVP-1542); die Rechnung, die sie anrechnet, löst
 * sie auf ({@see PrepaymentAdvance}).
 */
class OnlinePaymentAdapter extends AbstractPostingAdapter {
    public function __construct(PostingRuleResolver $rules, private readonly PrepaymentAdvance $advances) {
        parent::__construct($rules);
    }

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

    /**
     * Erstattung nach dem Buchen (Sicherheitsaudit 2026-10-04, li-8): der Satz
     * trägt weniger Rücklauf an der Forderung, als die Zahlung heute ausweist.
     * Zahlung und Erstattung teilen sich einen Vorschlag — der Ausweg ist
     * Storno und Neubuchung, der Buchungseingang muss den Fall aber zeigen.
     * Ebenso, wenn die Zahlung seit dem Buchen einer anderen Rechnung gehört
     * (Storno der Rechnung gibt die Vorauszahlung frei, MVP-1521).
     */
    public function changedSincePosting(Model $source, AccountingEntry $entry): bool {
        assert($source instanceof OnlinePayment);
        $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];
        // Als Anzahlung gebucht: Zuordnung zur Rechnung und Storno ändern den Satz nicht, nur eine Erstattung (MVP-1542).
        if (array_key_exists('refunded_amount', $snapshot)) {
            return abs((float) $source->refunded_amount->getAmount() - (float) $snapshot['refunded_amount']) >= 0.005;
        }
        $settled = isset($snapshot['settles_source_id']) ? (int) $snapshot['settles_source_id'] : null;
        if ($settled !== ($source->invoice_id !== null ? (int) $source->invoice_id : null)) {
            return true;
        }
        $booked = (float) $entry->lines()->whereNotNull('counterparty_id')->sum('debit');

        return abs((float) $source->refunded_amount->getAmount() - $booked) >= 0.005;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof OnlinePayment);

        $bookedOn = $this->advances->paidOn($source);
        $invoice = $source->invoice;
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        $foreign = $this->foreignCurrencyBlocker($organization, $source->currency);
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }

        $asAdvance = $this->advances->booksAsAdvance($organization, $source);
        $advance = $asAdvance ? $this->advances->party($organization, $source) : null;
        if ($asAdvance ? $advance === null : ! $invoice instanceof Invoice) {
            $blockers[] = (string) __('accounting.inbox.blocker.unsupported_target');
        }

        $gross = $source->gross_amount->getAmount();
        $fee = $source->fee_amount?->getAmount() ?? '0.00';
        $refunded = $source->refunded_amount->getAmount();
        $memo = $advance !== null
            ? (string) __('accounting.inbox.memo.online_prepayment', ['provider' => $source->provider, 'customer' => $advance['customer']->name])
            : (string) __('accounting.inbox.memo.online_payment', [
                'provider' => $source->provider,
                'invoice' => $invoice instanceof Invoice ? (string) $invoice->number : '—',
            ]);

        $transit = $this->rule($organization, PostingAccountRole::PaymentTransit, [], $bookedOn);
        if ($transit === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::PaymentTransit);
        }

        $customer = match (true) {
            $advance !== null => (int) $advance['customer']->id,
            $invoice instanceof Invoice => $invoice->customer_id,
            default => null,
        };
        $pairs = [];
        if ($advance !== null) {
            $pairs = $this->advancePairs($organization, $source, $advance['rate'], $customer, $transit, $bookedOn, $blockers);
        } elseif (! $asAdvance) {
            $receivable = $this->rule($organization, PostingAccountRole::Receivable, [], $bookedOn);
            if ($receivable === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::Receivable);
            }
            if ($transit !== null && $receivable !== null) {
                // Geldtransit an Forderung; eine Erstattung dreht den erstatteten Teil zurück.
                $pairs[] = [PostingAccountRole::PaymentTransit, $transit, $gross, '0.00', null, null];
                $pairs[] = [PostingAccountRole::Receivable, $receivable, '0.00', $gross, $customer, null];
                if (! NumberHelper::isZeroPrecise($refunded)) {
                    $pairs[] = [PostingAccountRole::Receivable, $receivable, $refunded, '0.00', $customer, null];
                    $pairs[] = [PostingAccountRole::PaymentTransit, $transit, '0.00', $refunded, null, null];
                }
            }
        }
        if (! NumberHelper::isZeroPrecise($fee)) {
            $fees = $this->rule($organization, PostingAccountRole::PaymentFees, [], $bookedOn);
            if ($fees === null || $transit === null) {
                $blockers[] = $this->missingRuleBlocker(PostingAccountRole::PaymentFees);
            } else {
                $pairs[] = [PostingAccountRole::PaymentFees, $fees, $fee, '0.00', null, null];
                $pairs[] = [PostingAccountRole::PaymentTransit, $transit, '0.00', $fee, null, null];
            }
        }

        foreach ($pairs as [$role, $rule, $debit, $credit, $customerId, $taxAmount]) {
            $line = $this->line($role, $rule, $debit, $credit, $memo, $customerId !== null ? MorphMap::alias(Customer::class) : null, $customerId, $taxAmount);
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
            documentReference: $advance === null && $invoice instanceof Invoice ? (string) $invoice->number : null,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: $memo,
            extra: match (true) {
                // Die Anzahlung trägt keine Forderungszeile; gebuchte Erstattung und Satz stehen deshalb im Snapshot.
                $advance !== null => ['refunded_amount' => $refunded, 'advance_rate' => $advance['rate']],
                $invoice instanceof Invoice && ! $asAdvance => [
                    'settles_source_type' => $invoice->getMorphClass(),
                    'settles_source_id' => $invoice->getKey(),
                    'settlement_kind' => SettlementKind::Payment->value,
                ],
                default => [],
            },
        );
    }

    /**
     * Mindest-Istversteuerung: Geldtransit an erhaltene Anzahlungen netto und
     * Umsatzsteuer; eine Erstattung kehrt den erstatteten Teil im selben
     * Verhältnis um.
     *
     * @param  numeric-string  $rate
     * @param  list<string>  $blockers
     * @return list<array{0: PostingAccountRole, 1: AccountingPostingRule, 2: numeric-string, 3: numeric-string, 4: int|null, 5: numeric-string|null}>
     */
    private function advancePairs(Organization $organization, OnlinePayment $payment, string $rate, ?int $customerId, ?AccountingPostingRule $transit, CarbonImmutable $on, array &$blockers): array {
        $context = ['tax_rate' => $rate];
        ['advance' => $advance, 'tax' => $tax] = $this->advances->rules($organization, $rate, $on);
        if ($advance === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::AdvanceReceived, $context);
        }
        $taxed = ! NumberHelper::isZeroPrecise($rate);
        if ($taxed && $tax === null) {
            $blockers[] = $this->missingRuleBlocker(PostingAccountRole::TaxOutput, $context);
        }
        if ($transit === null || $advance === null || ($taxed && $tax === null)) {
            return [];
        }

        [$net, $taxAmount] = $this->advances->split($payment->gross_amount, $rate);
        $pairs = [[PostingAccountRole::PaymentTransit, $transit, $payment->gross_amount->getAmount(), '0.00', null, null]];
        $pairs[] = [PostingAccountRole::AdvanceReceived, $advance, '0.00', $net, $customerId, null];
        if ($taxed && ! NumberHelper::isZeroPrecise($taxAmount)) {
            $pairs[] = [PostingAccountRole::TaxOutput, $tax, '0.00', $taxAmount, null, $taxAmount];
        }

        if ($payment->refunded_amount->isPositive()) {
            [$refundNet, $refundTax] = $this->advances->split($payment->refunded_amount, $rate);
            $pairs[] = [PostingAccountRole::AdvanceReceived, $advance, $refundNet, '0.00', $customerId, null];
            if ($taxed && ! NumberHelper::isZeroPrecise($refundTax)) {
                // Der Steuerbetrag trägt das Vorzeichen der Wirkung — die Erstattung mindert die Umsatzsteuer.
                $pairs[] = [PostingAccountRole::TaxOutput, $tax, $refundTax, '0.00', null, NumberHelper::negatePrecise($refundTax)];
            }
            $pairs[] = [PostingAccountRole::PaymentTransit, $transit, '0.00', $payment->refunded_amount->getAmount(), null, null];
        }

        return $pairs;
    }
}
