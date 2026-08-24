<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting\Adapters;

use App\Enums\Finance\{AllocationKind, PostingAccountRole, PostingSourceKind, SettlementKind};
use App\Models\{Expense, Invoice, Organization};
use App\Models\Finance\{BankTransaction, PaymentAllocation};
use App\Services\Accounting\Posting\{PostingProposal, PostingProposalLine};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Bestätigte Zahlung → Bank an Forderung bzw. Verbindlichkeit an Bank
 * (Feature 125, MVP-674).
 *
 * Der Adapter **konsumiert** die vorhandene Zuordnung aus dem
 * Zahlungsabgleich ({@see PaymentAllocation}), er baut sie nicht nach. Ein
 * zweiter Matching-Bestand wäre eine zweite Wahrheit über dieselbe Zahlung.
 *
 * Ein Rückläufer erscheint im Bestand als Zuordnung mit **negativem** Betrag
 * (Chargeback). Der Adapter dreht dafür die Seiten — so entsteht die
 * Gegenbewegung, statt dass eine Buchung verschwindet.
 */
class PaymentAdapter extends AbstractPostingAdapter {
    public function kind(): PostingSourceKind {
        return PostingSourceKind::Payment;
    }

    /** @return Collection<int, Model> */
    public function candidates(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        /** @var Collection<int, Model> $allocations */
        $allocations = PaymentAllocation::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('confirmed_at')
            ->whereHas('transaction', function ($query) use ($from, $to): void {
                $query->whereDate('booking_date', '>=', $from->toDateString())
                    ->whereDate('booking_date', '<=', $to->toDateString());
            })
            ->with(['transaction', 'allocatable'])
            ->orderBy('id')
            ->get();

        return $allocations;
    }

    public function proposalFor(Organization $organization, Model $source): PostingProposal {
        assert($source instanceof PaymentAllocation);

        $transaction = $source->transaction;
        $bookedOn = CarbonImmutable::parse(
            $transaction instanceof BankTransaction ? $transaction->booking_date : now(),
        )->startOfDay();
        $blockers = [];
        $lines = [];
        $ruleVersions = [];

        // Die Währung führt der Bankumsatz. Ohne belegbare Umrechnung nach
        // BMF-Monatskursen (§ 16 Abs. 6 UStG) wäre eine Fremdwährungszahlung
        // eine Buchung, die einen CHF-Betrag als Euro ausgibt.
        $foreign = $transaction instanceof BankTransaction
            ? $this->foreignCurrencyBlocker($organization, $transaction->currency)
            : null;
        if ($foreign !== null) {
            $blockers[] = $foreign;
        }

        // `amount` ist decimal:2 — Vorzeichen und Betrag ohne Float-Umweg.
        $amount = NumberHelper::normalizeDecimalString((string) $source->amount);
        $isRefund = NumberHelper::isNegativePrecise($amount);
        $absolute = NumberHelper::roundPrecise(NumberHelper::absPrecise($amount), 2);

        if (NumberHelper::isZeroPrecise($absolute)) {
            $blockers[] = (string) __('accounting.inbox.blocker.no_amount');
        }

        $target = $source->allocatable;
        [$counterRole, $targetIsReceivable] = match (true) {
            $target instanceof Invoice => [PostingAccountRole::Receivable, true],
            $target instanceof Expense => [PostingAccountRole::EmployeePayable, false],
            default => [null, true],
        };

        if ($counterRole === null) {
            $blockers[] = (string) __('accounting.inbox.blocker.unsupported_target');
        }

        // Skonto ist keine Zahlung: Er mindert die Forderung ohne Geldfluss
        // und braucht deshalb ein eigenes Konto.
        $isDiscount = $source->kind === AllocationKind::Skonto;
        $moneyRole = $isDiscount ? PostingAccountRole::Discount : PostingAccountRole::Bank;

        $moneyRule = $this->rule($organization, $moneyRole, [], $bookedOn);
        if ($moneyRule === null) {
            $blockers[] = $this->missingRuleBlocker($moneyRole);
        }

        $counterRule = $counterRole !== null ? $this->rule($organization, $counterRole, [], $bookedOn) : null;
        if ($counterRole !== null && $counterRule === null) {
            $blockers[] = $this->missingRuleBlocker($counterRole);
        }

        $purpose = $transaction instanceof BankTransaction ? (string) $transaction->purpose : '';

        if ($moneyRule !== null && $counterRule !== null) {
            // Geldseite: Eingang beim Kunden ist Soll auf der Bank, eine
            // Erstattung an Mitarbeitende ist Haben — der Rückläufer dreht beides.
            $moneyOnDebit = $targetIsReceivable !== $isRefund;

            $moneyLine = $this->line(
                $moneyRole,
                $moneyRule,
                $moneyOnDebit ? $absolute : '0.00',
                $moneyOnDebit ? '0.00' : $absolute,
                $purpose,
            );
            $counterLine = $this->line(
                $counterRole,
                $counterRule,
                $moneyOnDebit ? '0.00' : $absolute,
                $moneyOnDebit ? $absolute : '0.00',
                $purpose,
                $target instanceof Invoice ? \App\Models\Customer::class : \App\Models\User::class,
                $target instanceof Invoice ? $target->customer_id : ($target instanceof Expense ? $target->user_id : null),
            );

            foreach ([$moneyLine, $counterLine] as $line) {
                if ($line instanceof PostingProposalLine) {
                    $lines[] = $line;
                }
            }
            $ruleVersions[] = $moneyRule->versionTag();
            $ruleVersions[] = $counterRule->versionTag();
        }

        return new PostingProposal(
            kind: $this->kind(),
            source: $source,
            sourceKey: $this->sourceKey($source),
            bookedOn: $bookedOn,
            memo: (string) __('accounting.inbox.memo.payment', [
                'kind' => $source->kind->label(),
                'target' => $this->targetLabel($target),
            ]),
            lines: $lines,
            blockers: array_values(array_unique($blockers)),
            documentOn: $bookedOn,
            documentReference: $target instanceof Invoice ? (string) $target->number : null,
            ruleVersion: implode(',', array_unique($ruleVersions)) ?: null,
            title: $this->targetLabel($target),
            extra: $this->settlementContext($source),
        );
    }

    /**
     * Zusatzangaben für den OPOS-Ausgleich: Welcher Beleg wird ausgeglichen,
     * mit welcher Art — und aus welcher Zuordnung stammt das.
     *
     * @return array<string, mixed>
     */
    public function settlementContext(PaymentAllocation $allocation): array {
        $target = $allocation->allocatable;
        if (! $target instanceof Model) {
            return [];
        }

        return [
            'settles_source_type' => $target::class,
            'settles_source_id' => $target->getKey(),
            'settlement_kind' => $this->settlementKind($allocation)->value,
            'payment_allocation_id' => $allocation->id,
        ];
    }

    private function settlementKind(PaymentAllocation $allocation): SettlementKind {
        if (NumberHelper::isNegativePrecise(NumberHelper::normalizeDecimalString((string) $allocation->amount))) {
            return SettlementKind::Reversal;
        }

        return match ($allocation->kind) {
            AllocationKind::Skonto => SettlementKind::Discount,
            AllocationKind::Overpayment => SettlementKind::Overpayment,
            AllocationKind::Chargeback => SettlementKind::Reversal,
            default => SettlementKind::Payment,
        };
    }

    private function targetLabel(mixed $target): string {
        return match (true) {
            $target instanceof Invoice => (string) $target->number,
            $target instanceof Expense => (string) $target->description,
            default => '—',
        };
    }
}
