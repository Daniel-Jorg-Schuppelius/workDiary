<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\{AllocationKind, MatchStatus};
use App\Models\{Expense, Invoice, User};
use App\Models\Finance\{BankTransaction, PaymentAllocation, PaymentReconciliationEvent};
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Bestätigung und Rücknahme von Zahlungszuordnungen (Feature 045, „Priorität 3").
 *
 * Grundprinzip: Der Import ändert NICHTS an Belegen. Erst {@see confirm()} wirkt
 * auf die Ziele (Invoice.status/paid_on bzw. Expense.reimbursed_at). Der
 * Bankumsatz selbst wird NIE inhaltlich verändert — nur sein match_status.
 * Jede Aktion schreibt ein {@see PaymentReconciliationEvent} (Hash-Kette).
 */
class ReconciliationService {
    public function __construct(private readonly MatchingService $matching) {}

    /**
     * Bestätigt eine oder mehrere Zuordnungen für einen Bankumsatz und setzt die
     * Wirkung auf die Ziele.
     *
     * @param  list<array{type: class-string<Invoice>|class-string<Expense>, id: int, amount: float, kind?: AllocationKind, note?: string|null}>  $allocations
     */
    public function confirm(BankTransaction $transaction, array $allocations, ?User $actor = null): BankTransaction {
        if ($allocations === []) {
            throw new BankImportException('noAllocations', (string) __('bank.reconcile.error.no_allocations'), []);
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($transaction, $allocations, $actorId): BankTransaction {
            $payloadTargets = [];

            foreach ($allocations as $alloc) {
                $target = $this->resolveTarget($transaction->organization_id, $alloc['type'], $alloc['id']);
                $amount = round((float) $alloc['amount'], 2);
                $kind = $alloc['kind'] ?? $this->deriveKind($target, $amount);

                /** @var PaymentAllocation $allocation */
                $allocation = PaymentAllocation::query()->create([
                    'organization_id' => $transaction->organization_id,
                    'bank_transaction_id' => $transaction->id,
                    'allocatable_type' => $target::class,
                    'allocatable_id' => $target->id,
                    'amount' => (string) $amount,
                    'kind' => $kind,
                    'note' => $alloc['note'] ?? null,
                    'confirmed_by_user_id' => $actorId,
                    'confirmed_at' => now(),
                ]);

                $this->applyEffect($target, $transaction);

                $payloadTargets[] = [
                    'allocation_id' => $allocation->id,
                    'target_type' => $target::class,
                    'target_id' => $target->id,
                    'amount' => (string) $amount,
                    'kind' => $kind->value,
                ];
            }

            $transaction->match_status = MatchStatus::Matched;
            $transaction->save();

            $this->recordEvent($transaction, 'confirmed', $actorId, ['allocations' => $payloadTargets]);

            return $transaction->refresh();
        });
    }

    /**
     * Nimmt eine Zuordnung reversibel zurück: Allocation soft-deleten, die
     * Wirkung am Ziel nur dann zurücknehmen, wenn DIESE Zahlung die Wirkung
     * gesetzt hatte. Der Bankumsatz wird NIE verändert (nur match_status).
     */
    public function unmatch(PaymentAllocation $allocation, ?User $actor = null): PaymentAllocation {
        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($allocation, $actorId): PaymentAllocation {
            $target = $allocation->allocatable;
            $transaction = $allocation->transaction;

            $allocation->delete(); // SoftDelete — reversibel, Nachweis bleibt.

            if ($target instanceof Invoice) {
                $this->revertInvoice($target);
            } elseif ($target instanceof Expense) {
                $this->revertExpense($target, $allocation);
            }

            // Verbleiben keine aktiven Zuordnungen, ist der Umsatz wieder offen.
            if ($transaction !== null) {
                $remaining = $transaction->allocations()->count();
                $transaction->match_status = $remaining > 0 ? MatchStatus::Matched : MatchStatus::Unmatched;
                $transaction->save();

                $this->recordEvent($transaction, 'unmatched', $actorId, [
                    'allocation_id' => $allocation->id,
                    'target_type' => $allocation->allocatable_type,
                    'target_id' => $allocation->allocatable_id,
                ]);
            }

            return $allocation;
        });
    }

    /**
     * Lastschrift-Rückläufer-Workflow (MVP-334, GoBD-konform): kompensiert die
     * ursprüngliche Zuordnung, statt sie zu löschen.
     *
     *  - Die ORIGINAL-Zuordnung bleibt unverändert aktiv (Historie: die
     *    Zahlung ist tatsächlich geflossen).
     *  - Auf dem Rückläufer-Umsatz entsteht eine Chargeback-Zuordnung mit
     *    NEGATIVEM Betrag auf dasselbe Ziel — die Deckungssumme sinkt, der
     *    offene Posten öffnet sich wieder (Invoice zurück auf issued/
     *    partially_paid, Expense-Erstattung zurückgenommen).
     *  - Der Grund (z. B. ISO-Rückgabecode AC04) wird an der Kompensation und
     *    im Hash-Ketten-Event dokumentiert.
     *
     * @throws BankImportException
     */
    public function processReturn(
        BankTransaction $returnTransaction,
        PaymentAllocation $original,
        ?string $reason = null,
        ?User $actor = null,
    ): BankTransaction {
        // Org-Isolation hart erzwingen (Whitebox-Konvention: nie über die
        // Mandantengrenze kompensieren).
        if ((int) $original->organization_id !== (int) $returnTransaction->organization_id) {
            throw new BankImportException('targetNotFound', (string) __('bank.reconcile.error.target_not_found'), [
                'allocation_id' => $original->id,
            ]);
        }

        if ((int) $original->bank_transaction_id === (int) $returnTransaction->id) {
            throw new BankImportException('invalidReturn', (string) __('bank.return.error.same_transaction'), [
                'allocation_id' => $original->id,
            ]);
        }

        if ($original->trashed() || $original->kind === AllocationKind::Chargeback) {
            throw new BankImportException('invalidReturn', (string) __('bank.return.error.not_compensatable'), [
                'allocation_id' => $original->id,
            ]);
        }

        // Idempotenz: dieselbe Original-Zuordnung darf nur einmal kompensiert
        // werden (Marker exakt bzw. mit Leerzeichen-Grenze — kein Präfix-Treffer
        // von RET#1 auf RET#10).
        $marker = 'RET#' . $original->id;
        $alreadyCompensated = PaymentAllocation::query()
            ->where('organization_id', $returnTransaction->organization_id)
            ->where('kind', AllocationKind::Chargeback)
            ->where('allocatable_type', $original->allocatable_type)
            ->where('allocatable_id', $original->allocatable_id)
            ->where(fn($q) => $q->where('note', $marker)->orWhere('note', 'like', $marker . ' %'))
            ->exists();
        if ($alreadyCompensated) {
            throw new BankImportException('invalidReturn', (string) __('bank.return.error.already_compensated'), [
                'allocation_id' => $original->id,
            ]);
        }

        $actorId = $this->resolveActorId($actor);
        $reason = trim((string) ($reason ?? '')) !== '' ? trim((string) $reason) : $returnTransaction->return_reason;

        return DB::transaction(function () use ($returnTransaction, $original, $reason, $actorId): BankTransaction {
            $target = $original->allocatable;
            if (! $target instanceof Invoice && ! $target instanceof Expense) {
                throw new BankImportException('targetNotFound', (string) __('bank.reconcile.error.target_not_found'), [
                    'allocation_id' => $original->id,
                ]);
            }

            /** @var PaymentAllocation $compensation */
            $compensation = PaymentAllocation::query()->create([
                'organization_id' => $returnTransaction->organization_id,
                'bank_transaction_id' => $returnTransaction->id,
                'allocatable_type' => $original->allocatable_type,
                'allocatable_id' => $original->allocatable_id,
                'amount' => (string) round(-abs((float) $original->amount), 2),
                'kind' => AllocationKind::Chargeback,
                // Maschinenlesbarer Bezug auf die kompensierte Zuordnung +
                // dokumentierter Grund (GoBD-Nachvollziehbarkeit).
                'note' => trim('RET#' . $original->id . ' ' . (string) $reason),
                'confirmed_by_user_id' => $actorId,
                'confirmed_at' => now(),
            ]);

            if ($target instanceof Invoice) {
                $this->revertInvoice($target);
            } else {
                $this->revertExpense($target, $original);
            }

            $returnTransaction->match_status = MatchStatus::Matched;
            $returnTransaction->save();

            $this->recordEvent($returnTransaction, 'return_processed', $actorId, [
                'compensation_allocation_id' => $compensation->id,
                'original_allocation_id' => $original->id,
                'original_transaction_id' => $original->bank_transaction_id,
                'target_type' => $original->allocatable_type,
                'target_id' => $original->allocatable_id,
                'amount' => (string) round(-abs((float) $original->amount), 2),
                'reason' => $reason,
            ]);

            return $returnTransaction->refresh();
        });
    }

    /** Markiert einen Umsatz geprüft als nicht zuordenbar. */
    public function markUnassignable(BankTransaction $transaction, ?User $actor = null): BankTransaction {
        return $this->setStatus($transaction, MatchStatus::Unassignable, 'unassignable', $actor);
    }

    /** Legt einen Umsatz bewusst beiseite (z. B. Bankgebühr, interner Umsatz). */
    public function ignore(BankTransaction $transaction, ?User $actor = null): BankTransaction {
        return $this->setStatus($transaction, MatchStatus::Ignored, 'ignored', $actor);
    }

    private function setStatus(BankTransaction $transaction, MatchStatus $status, string $event, ?User $actor): BankTransaction {
        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($transaction, $status, $event, $actorId): BankTransaction {
            $transaction->match_status = $status;
            $transaction->save();
            $this->recordEvent($transaction, $event, $actorId, ['status' => $status->value]);

            return $transaction->refresh();
        });
    }

    private function applyEffect(Invoice|Expense $target, BankTransaction $transaction): void {
        if ($target instanceof Invoice) {
            $this->applyInvoiceEffect($target, $transaction);
        } else {
            $this->applyExpenseEffect($target, $transaction);
        }
    }

    /**
     * Rechnung gilt als bezahlt, sobald die Summe der aktiven Zuordnungen den
     * Rechnungsbetrag (abzüglich Skonto-Toleranz) deckt — dann status=paid und
     * paid_on=Buchungsdatum. Teilzahlung lässt den Status offen.
     */
    private function applyInvoiceEffect(Invoice $invoice, BankTransaction $transaction): void {
        $allocated = $this->allocatedSum($invoice);
        // MVP-416: beleggenaue Skonto-Kondition (Frist gegen Buchungsdatum) statt Pauschale.
        $minWithSkonto = $this->matching->minAcceptableFor($invoice, $transaction->booking_date);

        if ($invoice->status !== Invoice::STATUS_PAID
            && $allocated + MatchingService::CENT_TOLERANCE >= $minWithSkonto
        ) {
            $invoice->status = Invoice::STATUS_PAID;
            $invoice->paid_on = $transaction->booking_date;
            $invoice->saveQuietly();
        } elseif (
            // Teilzahlung (MVP-162): sichtbarer Zwischenstatus statt „offen".
            $allocated > 0
            && $allocated + MatchingService::CENT_TOLERANCE < $minWithSkonto
            && $invoice->status === Invoice::STATUS_ISSUED
        ) {
            $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
            $invoice->saveQuietly();
        }
    }

    private function applyExpenseEffect(Expense $expense, BankTransaction $transaction): void {
        if ($expense->reimbursed_at !== null) {
            return;
        }
        $expense->reimbursed_at = $transaction->booking_date;
        $expense->reimbursement_reference = $this->transactionReference($transaction);
        $expense->saveQuietly();
    }

    private function revertInvoice(Invoice $invoice): void {
        // Auch Teilzahlungen zurückdrehen: werden alle Zuordnungen entfernt,
        // darf die Rechnung nicht als „teilbezahlt" hängen bleiben
        // (Mahnwesen-relevant).
        if (! in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true)) {
            return;
        }
        // Nur zurücknehmen, wenn nach Wegfall dieser Zuordnung die Deckung fehlt.
        $allocated = $this->allocatedSum($invoice); // Allocation ist bereits soft-deleted.
        // MVP-416: beleggenaue Kondition (ohne Zahldatum: Kondition zählt) statt Pauschale.
        $minWithSkonto = $this->matching->minAcceptableFor($invoice);

        if ($allocated + MatchingService::CENT_TOLERANCE >= $minWithSkonto) {
            return; // weiterhin gedeckt — Status bleibt bestehen.
        }

        $invoice->status = $allocated > 0 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_ISSUED;
        $invoice->paid_on = null;
        $invoice->saveQuietly();
    }

    private function revertExpense(Expense $expense, PaymentAllocation $allocation): void {
        if ($expense->reimbursed_at === null) {
            return;
        }
        $reference = $this->transactionReference($allocation->transaction);
        // Nur zurücknehmen, wenn DIESE Erstattung die Markierung gesetzt hat.
        if ($expense->reimbursement_reference === $reference || $expense->reimbursement_reference === null) {
            $expense->reimbursed_at = null;
            $expense->reimbursement_reference = null;
            $expense->saveQuietly();
        }
    }

    /** Summe der aktiven (nicht soft-deleted) Zuordnungen auf eine Rechnung. */
    private function allocatedSum(Invoice $invoice): float {
        return (float) PaymentAllocation::query()
            ->where('allocatable_type', Invoice::class)
            ->where('allocatable_id', $invoice->id)
            ->sum('amount');
    }

    private function deriveKind(Invoice|Expense $target, float $amount): AllocationKind {
        if ($target instanceof Expense) {
            return AllocationKind::Reimbursement;
        }

        return $this->matching->kindForInvoice($amount, (float) $target->total);
    }

    private function transactionReference(?BankTransaction $transaction): ?string {
        if ($transaction === null) {
            return null;
        }

        return $transaction->end_to_end_id ?? ('TX-' . $transaction->id);
    }

    private function resolveTarget(?int $organizationId, string $type, int $id): Invoice|Expense {
        /** @var Invoice|Expense|null $target */
        $target = $type::query()->where('organization_id', $organizationId)->find($id);

        if (! $target instanceof Invoice && ! $target instanceof Expense) {
            throw new BankImportException('targetNotFound', (string) __('bank.reconcile.error.target_not_found'), [
                'type' => $type,
                'id' => $id,
            ]);
        }

        return $target;
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(BankTransaction $transaction, string $event, ?int $actorId, array $payload): void {
        PaymentReconciliationEvent::create([
            'organization_id' => $transaction->organization_id,
            'bank_transaction_id' => $transaction->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    private function resolveActorId(?User $actor): ?int {
        $id = $actor->id ?? Auth::id();

        return $id !== null ? (int) $id : null;
    }
}
