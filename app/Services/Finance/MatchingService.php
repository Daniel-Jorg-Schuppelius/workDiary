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

use App\Enums\Finance\AllocationKind;
use App\Models\{Expense, Invoice};
use App\Models\Finance\BankTransaction;
use App\Services\Finance\Banking\ReferenceExtractor;
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
            $foreignCurrency = strtoupper((string) $invoice->currency) !== strtoupper($transaction->currency);
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
            $foreignCurrency = strtoupper((string) $expense->currency) !== strtoupper($transaction->currency);
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
            if (\App\Support\Iban::hash($account->iban) === $hash) {
                return true;
            }
        }

        return false;
    }
}
