<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePaymentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubFeeClaimStatus, ClubFeePaymentMethod, ClubFeePaymentSource};
use App\Enums\Finance\{MandateStatus, PaymentRunKind, PaymentRunStatus};
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim, ClubFeeDunning, ClubFeePayment};
use App\Models\Document\DocumentDispatch;
use App\Models\Finance\{BankAccount, BankTransaction, PaymentAllocation, PaymentRun, PaymentRunItem, SepaMandate};
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Sepa\PaymentRunService;
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Validation\ValidationException;

/**
 * Zahlungen, Guthaben, Rücklastschrift, Mahnung und SEPA-Einzug für
 * Beitragsforderungen (Feature 159, MVP-851) — einzige Schreibstelle.
 * Dasselbe Geld wird nie zweimal angerechnet: eine per Einzug oder von Hand
 * gebuchte Zahlung wird vom Bankabgleich wiedererkannt statt erneut gebucht.
 * Exportiert oder zum Einzug vorgemerkt bedeutet niemals bezahlt.
 */
class ClubFeePaymentService {
    use AssertsStatusTransition;

    public const MAX_DUNNING_LEVEL = 3;

    public function __construct(
        private readonly ClubFeeService $fees,
        private readonly ClubFeeRunService $runs,
        private readonly PaymentRunService $paymentRuns,
    ) {}

    // ── Zahlungen ────────────────────────────────────────────────────────

    /**
     * Manuelle Zahlung (Überweisung, bar, …): wahlweise auf eine Forderung,
     * sonst der Fälligkeit nach auf offene Forderungen des Kontos
     * (Sammelzahlung für Familien); ein Rest bleibt als Guthaben.
     *
     * @param  array<string, mixed>  $data  amount, paid_on, method, reference, note, claim_id
     * @return Collection<int, ClubFeePayment>
     */
    public function recordPayment(ClubFeeAccount $account, array $data, ?User $actor = null): Collection {
        $amount = Money::of($this->amount($data['amount'] ?? null), $this->fees->defaultCurrency());
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => __('club.fees.error.amount_invalid')]);
        }
        $paidOn = CarbonImmutable::parse((string) ($data['paid_on'] ?? CarbonImmutable::today()->toDateString()))->startOfDay();
        $method = ClubFeePaymentMethod::tryFrom((string) ($data['method'] ?? '')) ?? ClubFeePaymentMethod::Transfer;
        $claimId = isset($data['claim_id']) && $data['claim_id'] !== '' ? (int) $data['claim_id'] : null;

        return DB::transaction(function () use ($account, $amount, $paidOn, $method, $data, $claimId, $actor): Collection {
            $remaining = $amount;
            $payments = collect();
            $targets = ClubFeeClaim::query()
                ->where('club_fee_account_id', $account->id)
                ->open()
                ->lockForUpdate()
                ->orderBy('due_on')
                ->orderBy('sequence')
                ->get()
                ->filter(fn(ClubFeeClaim $claim): bool => $claim->openAmount()->isPositive());
            if ($claimId !== null) {
                $preferred = $targets->firstWhere('id', $claimId);
                if ($preferred === null) {
                    throw ValidationException::withMessages(['claim_id' => __('club.fees.error.claim_not_open')]);
                }
                $targets = collect([$preferred])->merge($targets->reject(fn(ClubFeeClaim $c): bool => $c->id === $claimId));
            }
            foreach ($targets as $claim) {
                if (! $remaining->isPositive()) {
                    break;
                }
                $part = Money::min($remaining, $claim->openAmount());
                $payments->push($this->createPayment($account, $claim, $part, $paidOn, $method, ClubFeePaymentSource::Manual, [
                    'reference' => $this->nullableString($data['reference'] ?? null),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'created_by_user_id' => $actor?->id,
                ]));
                $remaining = $remaining->minus($part);
            }
            if ($remaining->isPositive()) {
                // Überzahlung: als Guthaben des Kontos, nicht als zweite Anrechnung.
                $payments->push($this->createPayment($account, null, $remaining, $paidOn, $method, ClubFeePaymentSource::Manual, [
                    'reference' => $this->nullableString($data['reference'] ?? null),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'created_by_user_id' => $actor?->id,
                ]));
            }

            return $payments;
        });
    }

    /** Nicht zugeordnetes Guthaben des Kontos (Summe der Zahlungen ohne Forderung). */
    public function creditBalance(ClubFeeAccount $account): Money {
        $rows = ClubFeePayment::query()->where('club_fee_account_id', $account->id)->whereNull('club_fee_claim_id')->get();
        if ($rows->isEmpty()) {
            return Money::zero($this->fees->defaultCurrency());
        }

        return Money::sum($rows->map(fn(ClubFeePayment $p): Money => $p->amount)->all());
    }

    /**
     * Guthaben mit offenen Forderungen verrechnen: negative Guthabenbuchung und
     * positive Zahlung je Forderung — nachvollziehbar, ohne doppelte Anrechnung.
     *
     * @return Collection<int, ClubFeePayment>
     */
    public function applyCredit(ClubFeeAccount $account, ?User $actor = null): Collection {
        return DB::transaction(function () use ($account, $actor): Collection {
            $credit = $this->creditBalance($account);
            $payments = collect();
            if (! $credit->isPositive()) {
                return $payments;
            }
            $today = CarbonImmutable::today();
            $claims = ClubFeeClaim::query()->where('club_fee_account_id', $account->id)->open()->lockForUpdate()->orderBy('due_on')->orderBy('sequence')->get();
            foreach ($claims as $claim) {
                if (! $credit->isPositive()) {
                    break;
                }
                $open = $claim->openAmount();
                if (! $open->isPositive()) {
                    continue;
                }
                $part = Money::min($credit, $open);
                $this->createPayment($account, null, $part->negated(), $today, ClubFeePaymentMethod::Other, ClubFeePaymentSource::Credit, ['note' => (string) __('club.fees.label.credit_applied_to', ['number' => $claim->number]), 'created_by_user_id' => $actor?->id]);
                $payments->push($this->createPayment($account, $claim, $part, $today, ClubFeePaymentMethod::Other, ClubFeePaymentSource::Credit, ['note' => (string) __('club.fees.label.credit_applied'), 'created_by_user_id' => $actor?->id]));
                $credit = $credit->minus($part);
            }

            return $payments;
        });
    }

    /**
     * Rücklastschrift: kompensiert genau eine Zahlung (negativ), öffnet den
     * Restbetrag wieder und sperrt den erneuten Einzug bis zur Klärung. Eine
     * Bankgebühr wird separat als Korrekturforderung behandelt.
     */
    public function chargeback(ClubFeePayment $payment, ?string $reason, ?User $actor = null, ?string $bankFee = null, ?int $bankTransactionId = null, ?int $allocationId = null): ClubFeePayment {
        return DB::transaction(function () use ($payment, $reason, $actor, $bankFee, $bankTransactionId, $allocationId): ClubFeePayment {
            /** @var ClubFeePayment $payment */
            $payment = ClubFeePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isChargeback() || ! $payment->amount->isPositive()) {
                throw ValidationException::withMessages(['payment' => __('club.fees.error.not_compensatable')]);
            }
            if (ClubFeePayment::query()->where('chargeback_of_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['payment' => __('club.fees.error.already_compensated')]);
            }
            $account = $payment->account()->firstOrFail();
            $claim = $payment->club_fee_claim_id !== null ? $payment->claim()->firstOrFail() : null;
            $reason = $this->nullableString($reason) ?? (string) __('club.fees.label.chargeback');
            $compensation = $this->createPayment($account, $claim, $payment->amount->negated(), CarbonImmutable::today(), $payment->method, ClubFeePaymentSource::Chargeback, [
                'reference' => $payment->reference,
                'note' => $reason,
                'chargeback_of_id' => $payment->id,
                'bank_transaction_id' => $bankTransactionId,
                'payment_allocation_id' => $allocationId,
                'created_by_user_id' => $actor?->id,
            ]);
            if ($claim !== null) {
                $claim->update(['collection_blocked_at' => now(), 'collection_block_reason' => mb_substr((string) __('club.fees.label.chargeback') . ': ' . $reason, 0, 255), 'payment_run_item_id' => null]);
                $claim->audit('club.fee.chargeback', ['payment_id' => $payment->id, 'amount' => $payment->amount->getAmount(), 'reason' => $reason]);
                $fee = $this->nullableString($bankFee);
                if ($fee !== null && $actor !== null && Money::of($this->amount($fee), $claim->currency)->isPositive()) {
                    $this->runs->createCorrection($claim, $actor, $this->amount($fee), (string) __('club.fees.label.bank_fee'), $reason);
                }
            }

            return $compensation;
        });
    }

    // ── Bankabgleich ─────────────────────────────────────────────────────

    /**
     * Zuordnung aus dem Bankabgleich: eine bereits gebuchte Zahlung (Einzug
     * oder manuell, gleicher Betrag, noch ohne Bankumsatz) wird wiedererkannt
     * und verknüpft — sonst entsteht eine Bankzahlung.
     */
    public function bookBankAllocation(ClubFeeClaim $claim, BankTransaction $transaction, PaymentAllocation $allocation): ClubFeePayment {
        return DB::transaction(function () use ($claim, $transaction, $allocation): ClubFeePayment {
            $amount = Money::of((string) $allocation->amount, $claim->currency);
            /** @var ClubFeePayment|null $existing */
            $existing = ClubFeePayment::query()
                ->where('club_fee_claim_id', $claim->id)
                ->whereNull('bank_transaction_id')
                ->whereIn('source', [ClubFeePaymentSource::Sepa->value, ClubFeePaymentSource::Manual->value])
                ->where('amount', $amount->getAmount())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                $existing->update(['bank_transaction_id' => $transaction->id, 'payment_allocation_id' => $allocation->id]);
                $existing->audit('club.fee.paymentMatched', ['bank_transaction_id' => $transaction->id]);

                return $existing->refresh();
            }
            $account = $claim->account()->firstOrFail();
            $open = $claim->openAmount();
            $toClaim = $open->isPositive() ? Money::min($amount, $open) : Money::zero($claim->currency);
            $payment = null;
            if ($toClaim->isPositive()) {
                $payment = $this->createPayment($account, $claim, $toClaim, CarbonImmutable::instance($transaction->booking_date), ClubFeePaymentMethod::Transfer, ClubFeePaymentSource::Bank, [
                    'reference' => $this->nullableString($transaction->purpose),
                    'bank_transaction_id' => $transaction->id,
                    'payment_allocation_id' => $allocation->id,
                ]);
            }
            $rest = $amount->minus($toClaim);
            if ($rest->isPositive()) {
                $credit = $this->createPayment($account, null, $rest, CarbonImmutable::instance($transaction->booking_date), ClubFeePaymentMethod::Transfer, ClubFeePaymentSource::Bank, [
                    'reference' => $this->nullableString($transaction->purpose),
                    'bank_transaction_id' => $transaction->id,
                    'payment_allocation_id' => $allocation->id,
                ]);
                $payment ??= $credit;
            }

            return $payment ?? throw ValidationException::withMessages(['amount' => __('club.fees.error.amount_invalid')]);
        });
    }

    /** Aufhebung einer Bankzuordnung: verknüpfte Zahlung lösen (wiedererkannt) oder Bankzahlung zurücknehmen. */
    public function revertBankAllocation(PaymentAllocation $allocation): void {
        DB::transaction(function () use ($allocation): void {
            foreach (ClubFeePayment::query()->where('payment_allocation_id', $allocation->id)->lockForUpdate()->get() as $payment) {
                if ($payment->source !== ClubFeePaymentSource::Bank) {
                    $payment->update(['bank_transaction_id' => null, 'payment_allocation_id' => null]);

                    continue;
                }
                $claim = $payment->club_fee_claim_id !== null ? $payment->claim()->first() : null;
                $payment->audit('club.fee.paymentReverted', ['allocation_id' => $allocation->id]);
                $payment->delete();
                if ($claim !== null) {
                    $this->applyToClaim($claim, $payment->amount->negated());
                }
            }
        });
    }

    /** Rücklastschrift aus dem Bankabgleich: die zur Originalzuordnung gebuchte Zahlung kompensieren. */
    public function chargebackFromBank(PaymentAllocation $original, BankTransaction $returnTransaction, PaymentAllocation $compensation, ?string $reason): void {
        $payments = ClubFeePayment::query()->where('payment_allocation_id', $original->id)->where('amount', '>', 0)->get();
        foreach ($payments as $payment) {
            if (ClubFeePayment::query()->where('chargeback_of_id', $payment->id)->exists()) {
                continue;
            }
            $this->chargeback($payment, $reason, null, null, $returnTransaction->id, $compensation->id);
        }
    }

    // ── Mahnung ──────────────────────────────────────────────────────────

    /**
     * Nächste Mahnstufe (Erinnerung → Mahnung → letzte Mahnung) mit Zahlungsziel
     * und optionaler Gebühr als verknüpfte Nachforderung; Mahnsperre und
     * Deckung werden geprüft.
     *
     * @param  array<string, mixed>  $options  pay_until, fee, note
     */
    public function dun(ClubFeeClaim $claim, array $options, User $actor): ClubFeeDunning {
        return DB::transaction(function () use ($claim, $options, $actor): ClubFeeDunning {
            /** @var ClubFeeClaim $claim */
            $claim = ClubFeeClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if (! $claim->isOverdue()) {
                throw ValidationException::withMessages(['level' => __('club.fees.error.not_overdue')]);
            }
            if ($claim->isDunningBlocked()) {
                throw ValidationException::withMessages(['level' => __('club.fees.error.dunning_blocked')]);
            }
            if ($claim->dunning_level >= self::MAX_DUNNING_LEVEL) {
                throw ValidationException::withMessages(['level' => __('club.fees.error.max_level')]);
            }
            $level = $claim->dunning_level + 1;
            $payUntil = $this->nullableString($options['pay_until'] ?? null);
            $fee = $this->nullableString($options['fee'] ?? null);
            $feeMoney = $fee !== null ? Money::of($this->amount($fee), $claim->currency) : null;
            $feeClaim = null;
            if ($feeMoney !== null && $feeMoney->isPositive()) {
                $feeClaim = $this->runs->createCorrection($claim, $actor, $feeMoney->getAmount(), (string) __('club.fees.label.dunning_fee', ['level' => $level]), (string) __('club.fees.label.dunning_level', ['level' => $level]));
            }
            $dunning = ClubFeeDunning::query()->create([
                'organization_id' => $claim->organization_id,
                'club_fee_claim_id' => $claim->id,
                'level' => $level,
                'issued_on' => CarbonImmutable::today()->toDateString(),
                'pay_until' => $payUntil !== null ? CarbonImmutable::parse($payUntil)->toDateString() : null,
                'fee' => $feeMoney !== null && $feeMoney->isPositive() ? $feeMoney : null,
                'currency' => $claim->currency->value,
                'fee_claim_id' => $feeClaim?->id,
                'note' => $this->nullableString($options['note'] ?? null),
                'created_by_user_id' => $actor->id,
            ]);
            $claim->update(['dunning_level' => $level, 'dunned_at' => now()]);
            $claim->audit('club.fee.dunned', ['level' => $level, 'pay_until' => $dunning->pay_until?->toDateString(), 'fee' => $feeMoney?->getAmount()]);

            return $dunning;
        });
    }

    /** Mahnung per E-Mail mit Zustellnachweis (eigenes Dokument, gleiche Kette wie die Mitteilung). */
    public function sendDunning(ClubFeeClaim $claim, ClubFeeDunning $dunning, string $email, ?User $actor = null): DocumentDispatch {
        $email = trim($email);
        if (! \CommonToolkit\Helper\Data\EmailHelper::isEmail($email)) {
            throw ValidationException::withMessages(['email' => __('club.fees.error.email_required')]);
        }
        $dispatch = DocumentDispatch::query()->create([
            'organization_id' => $claim->organization_id,
            'document_kind' => ClubFeeClaim::DOCUMENT_KIND,
            'document_id' => $claim->id,
            'channel' => DocumentDispatch::CHANNEL_EMAIL,
            'format' => 'pdf',
            'status' => 'queued',
            'recipient' => $email,
            'meta' => ['dunning_id' => $dunning->id, 'level' => $dunning->level],
            'created_by' => $actor?->id,
        ]);
        Mail::to($email)->queue(new \App\Mail\ClubFeeNoticeMail((int) $claim->id, (int) $dispatch->id, (int) $dunning->id));
        $claim->audit('club.fee.dunningMailed', ['level' => $dunning->level, 'to' => $email, 'dispatch_id' => $dispatch->id]);

        return $dispatch;
    }

    public function blockDunning(ClubFeeClaim $claim, string $reason, User $actor): ClubFeeClaim {
        $claim->update(['dunning_blocked_at' => now(), 'dunning_block_reason' => mb_substr(trim($reason), 0, 255)]);
        $claim->audit('club.fee.dunningBlocked', ['reason' => trim($reason), 'actor_id' => $actor->id]);

        return $claim->refresh();
    }

    public function unblockDunning(ClubFeeClaim $claim, User $actor): ClubFeeClaim {
        $claim->update(['dunning_blocked_at' => null, 'dunning_block_reason' => null]);
        $claim->audit('club.fee.dunningUnblocked', ['actor_id' => $actor->id]);

        return $claim->refresh();
    }

    /** Einzugssperre nach Klärung aufheben (z. B. nach Rücklastschrift). */
    public function unblockCollection(ClubFeeClaim $claim, User $actor): ClubFeeClaim {
        $claim->update(['collection_blocked_at' => null, 'collection_block_reason' => null]);
        $claim->audit('club.fee.collectionUnblocked', ['actor_id' => $actor->id]);

        return $claim->refresh();
    }

    // ── SEPA-Einzug ──────────────────────────────────────────────────────

    /** Mandat des Kontos: ausdrücklich gewähltes, sonst das aktive Mandat des Kunden. */
    public function mandateFor(ClubFeeAccount $account): ?SepaMandate {
        if ($account->sepa_mandate_id !== null) {
            /** @var SepaMandate|null $chosen */
            $chosen = SepaMandate::query()->whereKey($account->sepa_mandate_id)->where('customer_id', $account->customer_id)->first();
            if ($chosen !== null) {
                return $chosen;
            }
        }
        /** @var SepaMandate|null $mandate */
        $mandate = SepaMandate::query()->where('customer_id', $account->customer_id)->where('status', MandateStatus::Active->value)->orderByDesc('id')->first();

        return $mandate;
    }

    /**
     * Einzugsvorschlag: offene Restbeträge fälliger Forderungen mit nutzbarem
     * Mandat, ohne Sperre und ohne aktiven Einzugsversuch. Blockierte
     * Positionen werden mit Grund aufgeführt, nicht still ausgelassen.
     *
     * @return Collection<int, array{claim: ClubFeeClaim, account: ClubFeeAccount, mandate: SepaMandate|null, amount: Money, blocked: string|null}>
     */
    public function collectionProposals(Organization $organization, ?CarbonImmutable $today = null): Collection {
        $today ??= CarbonImmutable::today();
        $claims = ClubFeeClaim::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->where('due_on', '<', DateRange::dayAfter($today->addDays(14)))
            ->with('account')
            ->orderBy('due_on')
            ->orderBy('sequence')
            ->get();
        $rows = collect();
        foreach ($claims as $claim) {
            $open = $claim->openAmount();
            if (! $open->isPositive() || $claim->account === null) {
                continue;
            }
            $mandate = $this->mandateFor($claim->account);
            $blocked = match (true) {
                $claim->collection_blocked_at !== null => (string) __('club.fees.label.collection_blocked', ['reason' => (string) $claim->collection_block_reason]),
                $claim->payment_run_item_id !== null => (string) __('club.fees.label.collection_reserved'),
                $mandate === null => (string) __('club.fees.label.no_mandate'),
                ! $mandate->isUsable($today) => (string) __('club.fees.label.mandate_unusable'),
                default => null,
            };
            $rows->push(['claim' => $claim, 'account' => $claim->account, 'mandate' => $mandate, 'amount' => $open, 'blocked' => $blocked]);
        }

        return $rows;
    }

    /**
     * Sammellauf über die vorhandenen SEPA-Dienste: eine Position je Forderung
     * mit Mandat und eindeutiger Versuchsreferenz; der Versuch reserviert den
     * Betrag. Export (pain.008) bleibt dem Finanzmodul vorbehalten.
     *
     * @param  list<int>  $claimIds
     */
    public function createCollectionRun(BankAccount $bankAccount, User $actor, array $claimIds, ?CarbonImmutable $executionDate = null, ?string $label = null): PaymentRun {
        $today = CarbonImmutable::today();
        $proposals = $this->collectionProposals(Organization::query()->findOrFail($bankAccount->organization_id), $today)
            ->filter(fn(array $row): bool => in_array($row['claim']->id, $claimIds, true) && $row['blocked'] === null && $row['mandate'] !== null);
        if ($proposals->isEmpty()) {
            throw ValidationException::withMessages(['claim_ids' => __('club.fees.error.no_collection_positions')]);
        }

        return DB::transaction(function () use ($bankAccount, $actor, $proposals, $executionDate, $label, $today): PaymentRun {
            $earliest = $proposals->map(fn(array $row): CarbonImmutable => $this->paymentRuns->earliestCollection($row['mandate'], $today))->max() ?? $today;
            $run = PaymentRun::query()->create([
                'organization_id' => $bankAccount->organization_id,
                'bank_account_id' => $bankAccount->id,
                'kind' => PaymentRunKind::DirectDebit->value,
                'status' => PaymentRunStatus::Draft->value,
                'label' => $label ?? (string) __('club.fees.label.collection_run', ['date' => $today->format('d.m.Y')]),
                'execution_date' => $executionDate !== null && $executionDate->greaterThanOrEqualTo($earliest) ? $executionDate : $earliest,
                'created_by' => $actor->id,
            ]);
            foreach ($proposals as $row) {
                /** @var ClubFeeClaim $claim */
                $claim = ClubFeeClaim::query()->whereKey($row['claim']->id)->lockForUpdate()->firstOrFail();
                if ($claim->payment_run_item_id !== null) {
                    continue; // paralleler Lauf hat die Forderung bereits reserviert
                }
                /** @var SepaMandate $mandate */
                $mandate = $row['mandate'];
                $attempt = $claim->collection_attempts + 1;
                $item = PaymentRunItem::query()->create([
                    'organization_id' => $run->organization_id,
                    'payment_run_id' => $run->id,
                    'customer_id' => $claim->customer_id,
                    'sepa_mandate_id' => $mandate->id,
                    'party_name' => mb_substr((string) ($row['account']->name ?? '—'), 0, 70),
                    'iban' => $mandate->iban,
                    'bic' => $mandate->bic,
                    'amount' => $row['amount']->getAmount(),
                    'reference' => mb_substr((string) __('club.fees.label.collection_reference', ['number' => $claim->number]), 0, 140),
                    // Jeder Versuch erhält eine neue, eindeutige Referenz.
                    'end_to_end_id' => mb_substr($claim->number . '-' . $attempt, 0, 35),
                ]);
                $claim->update(['payment_run_item_id' => $item->id, 'collection_attempts' => $attempt]);
                $claim->audit('club.fee.collectionScheduled', ['run_id' => $run->id, 'attempt' => $attempt, 'amount' => $row['amount']->getAmount()]);
            }
            $run = $this->paymentRuns->recalculate($run);
            if ($run->items()->count() === 0) {
                $run->forceFill(['status' => PaymentRunStatus::Cancelled->value])->save();
                throw ValidationException::withMessages(['claim_ids' => __('club.fees.error.no_collection_positions')]);
            }
            $run->audit('paymentRun.created', ['source' => 'club-fees', 'positions' => $run->items()->count()]);

            return $run;
        });
    }

    /** Abbruch vor dem Export: Reservierungen lösen; ein neuer Versuch erhält später eine neue Referenz. */
    public function cancelCollectionRun(PaymentRun $run): PaymentRun {
        return DB::transaction(function () use ($run): PaymentRun {
            $run = $this->paymentRuns->cancel($run);
            ClubFeeClaim::query()->whereIn('payment_run_item_id', $run->items()->pluck('id'))->update(['payment_run_item_id' => null]);

            return $run;
        });
    }

    /**
     * Zahlungseingang eines exportierten Einzugs buchen (nach Bankgutschrift):
     * je Position eine Einzugszahlung; der spätere Bankabgleich erkennt sie
     * am Betrag wieder. Export allein bucht nichts.
     */
    public function settleCollectionRun(PaymentRun $run, CarbonImmutable $paidOn, User $actor): int {
        if (! $run->isExported()) {
            throw ValidationException::withMessages(['run' => __('club.fees.error.run_not_exported')]);
        }

        return DB::transaction(function () use ($run, $paidOn, $actor): int {
            $count = 0;
            foreach ($run->items()->get() as $item) {
                /** @var ClubFeeClaim|null $claim */
                $claim = ClubFeeClaim::query()->where('payment_run_item_id', $item->id)->lockForUpdate()->first();
                if ($claim === null || $claim->account === null) {
                    continue;
                }
                $this->createPayment($claim->account, $claim, Money::of((string) $item->amount, $claim->currency), $paidOn, ClubFeePaymentMethod::Sepa, ClubFeePaymentSource::Sepa, [
                    'reference' => $item->end_to_end_id,
                    'payment_run_item_id' => $item->id,
                    'created_by_user_id' => $actor->id,
                ]);
                $claim->update(['payment_run_item_id' => null]);
                $count++;
            }
            $run->audit('paymentRun.settled', ['source' => 'club-fees', 'positions' => $count, 'paid_on' => $paidOn->toDateString()]);

            return $count;
        });
    }

    /** @param array<string, mixed> $extra */
    private function createPayment(ClubFeeAccount $account, ?ClubFeeClaim $claim, Money $amount, CarbonImmutable $paidOn, ClubFeePaymentMethod $method, ClubFeePaymentSource $source, array $extra = []): ClubFeePayment {
        $payment = ClubFeePayment::query()->create([
            'organization_id' => $account->organization_id,
            'club_fee_account_id' => $account->id,
            'club_fee_claim_id' => $claim?->id,
            'amount' => $amount,
            'currency' => $amount->getCurrency()->value,
            'paid_on' => $paidOn->toDateString(),
            'method' => $method->value,
            'source' => $source->value,
        ] + $extra);
        $payment->audit('club.fee.paymentRecorded', ['amount' => $amount->getAmount(), 'claim' => $claim?->number, 'source' => $source->value]);
        if ($claim !== null) {
            $this->applyToClaim($claim, $amount);
        }

        return $payment;
    }

    /** Zahlungsstand fortschreiben; Status folgt dem Restbetrag (Rücklastschrift öffnet wieder). */
    private function applyToClaim(ClubFeeClaim $claim, Money $delta): void {
        /** @var ClubFeeClaim $claim */
        $claim = ClubFeeClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
        if ($claim->status === ClubFeeClaimStatus::Cancelled) {
            throw ValidationException::withMessages(['claim_id' => __('club.fees.error.claim_not_open')]);
        }
        $paid = $claim->paid_amount->plus($delta);
        $target = match (true) {
            $claim->total->isPositive() && $paid->greaterThanOrEqual($claim->total) => ClubFeeClaimStatus::Paid,
            $claim->total->isNegative() && $paid->lessThanOrEqual($claim->total) => ClubFeeClaimStatus::Paid,
            $paid->isZero() => ClubFeeClaimStatus::Open,
            default => ClubFeeClaimStatus::PartiallyPaid,
        };
        $values = ['paid_amount' => $paid, 'paid_at' => $target === ClubFeeClaimStatus::Paid ? now() : null];
        if ($target !== $claim->status) {
            $this->assertStatusTransition($claim->status, $target);
            $values['status'] = $target->value;
        }
        if ($target === ClubFeeClaimStatus::Paid) {
            $values['payment_run_item_id'] = null;
        }
        $claim->update($values);
    }

    private function amount(mixed $value): string {
        $raw = $this->nullableString($value);
        $normalized = $raw !== null ? \CommonToolkit\Helper\Data\NumberHelper::normalizeDecimalStringOrNull($raw) : null;
        if ($normalized === null) {
            throw ValidationException::withMessages(['amount' => __('club.fees.error.amount_invalid')]);
        }

        return \CommonToolkit\ValueObjects\Decimal::of($normalized, 2)->getValue();
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
