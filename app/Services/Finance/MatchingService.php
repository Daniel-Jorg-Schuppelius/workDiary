<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\{AllocationKind, TransactionDirection};
use App\Models\{Expense, Invoice};
use App\Models\Finance\{BankTransaction, PaymentAllocation};
use App\Services\Finance\Banking\ReferenceExtractor;
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Score-basierte Zuordnungsvorschläge (Feature 045, „Priorität 3").
 *
 * Das Matching nutzt AUSSCHLIESSLICH unverschlüsselte Ableitungen des
 * Bankumsatzes (extracted_refs, counterparty_iban_hash, amount, dates) — die
 * verschlüsselten PII-Felder (Name/IBAN/Zweck) werden NICHT durchsucht.
 *
 * Regeln (alle org-scoped über das Modell-Scope):
 *  - Gutschrift (credit) ⇒ offene Rechnungen (Status nicht paid/cancelled).
 *  - Lastschrift (debit)  ⇒ freigegebene, noch nicht erstattete Spesen.
 *  - Fremdwährung (currency != Beleg-Währung) ⇒ kein Auto-Vorschlag.
 *  - Cent-Toleranz ±0,02; Skonto-Toleranz bis SKONTO_PERCENT % Unterzahlung.
 */
class MatchingService {
    /** Cent-Toleranz für „Betrag passt" (Rundungsdifferenz). */
    public const CENT_TOLERANCE = 0.02;

    /** Maximaler Skontoabzug, der noch als Vollzahlung gilt (Prozent). */
    public const SKONTO_PERCENT = 3.0;

    public const SCORE_REFERENCE = 60;

    public const SCORE_AMOUNT_EXACT = 30;

    public const SCORE_AMOUNT_SKONTO = 18;

    public const SCORE_IBAN = 25;

    public const SCORE_DATE_NEAR = 10;

    /**
     * Liefert die besten Zuordnungsvorschläge für einen Bankumsatz.
     *
     * @return list<array{target: Model, kind: AllocationKind, score: int, reasons: list<string>, open_amount: float, foreign_currency: bool}>
     */
    public function suggestFor(BankTransaction $transaction, int $limit = 5): array {
        $suggestions = $transaction->isCredit()
            ? $this->suggestInvoices($transaction)
            : $this->suggestExpenses($transaction);

        usort($suggestions, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * @return list<array{target: Model, kind: AllocationKind, score: int, reasons: list<string>, open_amount: float, foreign_currency: bool}>
     */
    private function suggestInvoices(BankTransaction $transaction): array {
        $amount = (float) $transaction->amount;
        $refs = $this->normalizedRefs($transaction);
        $results = [];

        /** @var \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->where('type', Invoice::TYPE_INVOICE)
            ->get();

        foreach ($invoices as $invoice) {
            $foreignCurrency = $invoice->currency !== $transaction->currency;
            $score = 0;
            $reasons = [];

            if ($this->referenceMatches($invoice->number, $refs)) {
                $score += self::SCORE_REFERENCE;
                $reasons[] = 'reference';
            }

            $total = (float) $invoice->total;
            [$amountScore, $amountReason] = $this->scoreAmount($amount, $total);
            if ($amountScore > 0) {
                $score += $amountScore;
                $reasons[] = $amountReason;
            }

            if ($this->dateNear($invoice->issued_on?->toDateString(), $transaction->booking_date->toDateString())) {
                $score += self::SCORE_DATE_NEAR;
                $reasons[] = 'date';
            }

            // Gegenpartei-IBAN gegen Kunden-Bankverbindung (Hash-Abgleich).
            if ($this->customerIbanMatches($invoice, $transaction)) {
                $score += self::SCORE_IBAN;
                $reasons[] = 'iban';
            }

            if ($score <= 0) {
                continue;
            }

            // Fremdwährung: nur als „manuell zu klären" anzeigen, nie als
            // belastbarer Auto-Vorschlag (Score gedeckelt, Skonto/Voll aus).
            if ($foreignCurrency) {
                $reasons[] = 'foreign_currency';
            }

            $results[] = [
                'target' => $invoice,
                'kind' => $this->kindForInvoice($amount, $total, $foreignCurrency),
                'score' => $foreignCurrency ? min($score, self::SCORE_REFERENCE) : $score,
                'reasons' => array_values(array_unique($reasons)),
                'open_amount' => round($total, 2),
                'foreign_currency' => $foreignCurrency,
            ];
        }

        return $results;
    }

    /**
     * @return list<array{target: Model, kind: AllocationKind, score: int, reasons: list<string>, open_amount: float, foreign_currency: bool}>
     */
    private function suggestExpenses(BankTransaction $transaction): array {
        $amount = (float) $transaction->amount;
        $refs = $this->normalizedRefs($transaction);
        $results = [];

        /** @var \Illuminate\Database\Eloquent\Collection<int, Expense> $expenses */
        $expenses = Expense::query()
            ->where('status', \App\Enums\Expense\ExpenseStatus::Approved->value)
            ->whereNull('reimbursed_at')
            ->get();

        foreach ($expenses as $expense) {
            $foreignCurrency = $expense->currency !== $transaction->currency;
            $score = 0;
            $reasons = [];

            if ($refs !== [] && $this->referenceMatches((string) $expense->id, $refs)) {
                $score += self::SCORE_REFERENCE;
                $reasons[] = 'reference';
            }

            $gross = (float) $expense->amount_gross;
            [$amountScore, $amountReason] = $this->scoreAmount($amount, $gross);
            if ($amountScore > 0) {
                $score += $amountScore;
                $reasons[] = $amountReason;
            }

            if ($score <= 0) {
                continue;
            }

            if ($foreignCurrency) {
                $reasons[] = 'foreign_currency';
            }

            $results[] = [
                'target' => $expense,
                'kind' => AllocationKind::Reimbursement,
                'score' => $foreignCurrency ? min($score, self::SCORE_REFERENCE) : $score,
                'reasons' => array_values(array_unique($reasons)),
                'open_amount' => round($gross, 2),
                'foreign_currency' => $foreignCurrency,
            ];
        }

        return $results;
    }

    /**
     * Auto-Split-Vorschlag für Sammelbuchungen (Toolkit-Folgepaket 2): je
     * gespeichertem TransactionDetail wird EIN Zuordnungsvorschlag gegen die
     * offenen Posten ermittelt — mit DERSELBEN Kandidatenlogik wie das
     * Einzel-Matching ({@see suggestFor}), nur mit Betrag, EndToEndId,
     * Gegenpartei-IBAN-Hash und Verwendungszweck-Referenzen des Details.
     * Match-Schlüssel damit implizit: Betrag + EndToEndId (reference+amount),
     * dann Gegenpartei-IBAN + Verwendungszweck-Heuristik (extracted refs).
     * Jedes Ziel wird nur EINMAL je Split vorgeschlagen (bester Score zuerst).
     *
     * @return list<array{index: int, detail: array<string, mixed>, suggestion: array{target: Model, kind: AllocationKind, score: int, reasons: list<string>, open_amount: float, foreign_currency: bool}|null}>
     */
    public function suggestSplitFor(BankTransaction $transaction): array {
        $details = $transaction->transactionDetails();
        if (count($details) < 2) {
            return [];
        }

        $rows = [];
        $taken = [];
        foreach ($details as $index => $detail) {
            $suggestion = null;
            foreach ($this->suggestFor($this->detailProbe($transaction, $detail)) as $candidate) {
                $key = $candidate['target']::class . '#' . $candidate['target']->getKey();
                if (isset($taken[$key])) {
                    continue;
                }
                $taken[$key] = true;
                $suggestion = $candidate;
                break;
            }

            $rows[] = [
                'index' => $index,
                'detail' => $detail,
                'suggestion' => $suggestion,
            ];
        }

        return $rows;
    }

    /**
     * Rückläufer-Kandidaten je Einzeltransaktion einer Sammel-Rücklastschrift
     * (Toolkit-Folgepaket 2): nur Details mit ISO-Rückgabegrund (isReturn)
     * werden betrachtet; je Detail läuft {@see suggestReturnOrigins} mit
     * Betrag, EndToEndId und Mandatsreferenz DES DETAILS, sodass
     * {@see ReconciliationService::processReturn()} je ursprünglicher
     * Zuordnung vorgeschlagen werden kann.
     *
     * @return array<int, list<array{allocation: PaymentAllocation, score: int, reasons: list<string>}>> Detail-Index ⇒ Kandidaten.
     */
    public function suggestReturnOriginsForDetails(BankTransaction $returnTransaction, int $limit = 5): array {
        $result = [];
        foreach ($returnTransaction->transactionDetails() as $index => $detail) {
            $reason = $detail['return_reason'] ?? null;
            if (! is_string($reason) || $reason === '') {
                continue;
            }

            $origins = $this->suggestReturnOrigins($this->detailProbe($returnTransaction, $detail), $limit);
            if ($origins !== []) {
                $result[$index] = $origins;
            }
        }

        return $result;
    }

    /**
     * Nicht persistierte Kopie des Bankumsatzes mit den Werten EINES
     * TransactionDetails (Betrag, Richtung aus dem Vorzeichen, Referenzen,
     * IBAN-Hash, Zweck-Referenzen) — so laufen Split- und Rückläufer-Matching
     * über exakt dieselbe Kandidatenlogik wie das Einzel-Matching.
     *
     * @param  array<string, mixed>  $detail
     */
    private function detailProbe(BankTransaction $transaction, array $detail): BankTransaction {
        $signed = (float) ($detail['amount'] ?? 0.0);
        $endToEnd = isset($detail['end_to_end_id']) && is_string($detail['end_to_end_id']) ? $detail['end_to_end_id'] : null;
        $purpose = isset($detail['purpose']) && is_string($detail['purpose']) ? $detail['purpose'] : null;

        $probe = $transaction->replicate();
        // replicate() lässt den Primärschlüssel aus — für die Selbst-Ausschluss-
        // Klausel in suggestReturnOrigins wird er explizit mitgegeben.
        $probe->id = $transaction->id;
        $probe->amount = number_format(abs($signed), 2, '.', '');
        $probe->direction = $signed < 0 ? TransactionDirection::Debit : TransactionDirection::Credit;
        $probe->end_to_end_id = $endToEnd;
        $probe->mandate_ref = isset($detail['mandate_ref']) && is_string($detail['mandate_ref']) ? $detail['mandate_ref'] : null;
        $probe->counterparty_iban_hash = isset($detail['counterparty_iban_hash']) && is_string($detail['counterparty_iban_hash']) ? $detail['counterparty_iban_hash'] : null;
        $probe->extracted_refs = ReferenceExtractor::extract($purpose, $endToEnd);
        $probe->return_reason = isset($detail['return_reason']) && is_string($detail['return_reason']) ? $detail['return_reason'] : null;

        return $probe;
    }

    /**
     * Kandidaten für die Rückläufer-Kompensation (MVP-334): aktive, noch nicht
     * kompensierte Zuordnungen derselben Organisation, deren Betrag zum
     * Rückläufer passt; End-to-End-Referenz bzw. Mandatsreferenz des
     * ursprünglichen Umsatzes erhöhen den Score. Nur unverschlüsselte
     * Ableitungen (amount, end_to_end_id, mandate_ref) werden verglichen.
     *
     * @return list<array{allocation: PaymentAllocation, score: int, reasons: list<string>}>
     */
    public function suggestReturnOrigins(BankTransaction $returnTransaction, int $limit = 5): array {
        $amount = abs((float) $returnTransaction->amount);
        $results = [];

        /** @var \Illuminate\Database\Eloquent\Collection<int, PaymentAllocation> $allocations */
        $allocations = PaymentAllocation::query()
            ->where('organization_id', $returnTransaction->organization_id)
            ->where('kind', '!=', AllocationKind::Chargeback->value)
            ->where('bank_transaction_id', '!=', $returnTransaction->id)
            ->with('transaction')
            ->get();

        foreach ($allocations as $allocation) {
            $score = 0;
            $reasons = [];

            if (abs(abs((float) $allocation->amount) - $amount) <= self::CENT_TOLERANCE) {
                $score += self::SCORE_AMOUNT_EXACT;
                $reasons[] = 'amount';
            }

            $originalTx = $allocation->transaction;
            if ($originalTx !== null) {
                if ($returnTransaction->end_to_end_id !== null
                    && $originalTx->end_to_end_id === $returnTransaction->end_to_end_id
                ) {
                    $score += self::SCORE_REFERENCE;
                    $reasons[] = 'reference';
                }
                if ($returnTransaction->mandate_ref !== null
                    && $originalTx->mandate_ref === $returnTransaction->mandate_ref
                ) {
                    $score += self::SCORE_IBAN;
                    $reasons[] = 'mandate';
                }
                if ($this->dateNear($originalTx->booking_date->toDateString(), $returnTransaction->booking_date->toDateString())) {
                    $score += self::SCORE_DATE_NEAR;
                    $reasons[] = 'date';
                }
            }

            if ($score <= 0) {
                continue;
            }

            $results[] = [
                'allocation' => $allocation,
                'score' => $score,
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Bewertet die Betragsnähe: exakt (±Cent-Toleranz) oder mit zulässigem
     * Skonto (Unterzahlung bis SKONTO_PERCENT %).
     *
     * @return array{0: int, 1: string}
     */
    private function scoreAmount(float $paid, float $due): array {
        if ($due <= 0.0) {
            return [0, ''];
        }
        if (abs($paid - $due) <= self::CENT_TOLERANCE) {
            return [self::SCORE_AMOUNT_EXACT, 'amount'];
        }

        $minWithSkonto = $due * (1 - self::SKONTO_PERCENT / 100);
        if ($paid < $due && $paid >= $minWithSkonto - self::CENT_TOLERANCE) {
            return [self::SCORE_AMOUNT_SKONTO, 'skonto'];
        }

        return [0, ''];
    }

    /** Voll/Über/Teil anhand des gezahlten gegen den offenen Betrag. */
    public function kindForInvoice(float $paid, float $due, bool $foreignCurrency = false): AllocationKind {
        if ($foreignCurrency) {
            return AllocationKind::Partial;
        }
        $minWithSkonto = $due * (1 - self::SKONTO_PERCENT / 100);
        if ($paid + self::CENT_TOLERANCE >= $due) {
            return $paid - $due > self::CENT_TOLERANCE ? AllocationKind::Overpayment : AllocationKind::Payment;
        }
        if ($paid >= $minWithSkonto - self::CENT_TOLERANCE) {
            return AllocationKind::Payment; // Skontozahlung gilt als Vollzahlung.
        }

        return AllocationKind::Partial;
    }

    /** @param list<string> $refs */
    private function referenceMatches(?string $number, array $refs): bool {
        if ($number === null || $number === '' || $refs === []) {
            return false;
        }
        $normalized = ReferenceExtractor::normalize($number);

        return in_array($normalized, $refs, true);
    }

    /** @return list<string> Normalisierte Belegnummern-Kandidaten des Umsatzes. */
    private function normalizedRefs(BankTransaction $transaction): array {
        $refs = [];
        foreach ($transaction->extracted_refs ?? [] as $ref) {
            $refs[] = ReferenceExtractor::normalize((string) $ref);
        }

        return array_values(array_unique(array_filter($refs, static fn(string $r): bool => $r !== '')));
    }

    private function dateNear(?string $reference, string $booking, int $days = 45): bool {
        if ($reference === null) {
            return false;
        }

        return abs(strtotime($booking) - strtotime($reference)) <= $days * 86400;
    }

    private function customerIbanMatches(Invoice $invoice, BankTransaction $transaction): bool {
        $hash = $transaction->counterparty_iban_hash;
        if ($hash === null) {
            return false;
        }

        foreach ($invoice->customer->bankAccounts as $account) {
            if (BankHelper::hashIBAN($account->iban) === $hash) {
                return true;
            }
        }

        return false;
    }
}
