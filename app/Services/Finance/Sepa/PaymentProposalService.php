<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentProposalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Sepa;

use App\Models\{IncomingEInvoice, Supplier};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Zahlungsvorschlag (Feature 120, MVP-609).
 *
 * Vorschlag heißt Vorschlag: Hier entsteht nichts Buchhalterisches, sondern
 * eine Liste mit dem wirtschaftlichsten Ausführungsdatum je Rechnung. Ob und
 * was gezahlt wird, entscheidet der Mensch im nächsten Schritt.
 */
class PaymentProposalService {
    /**
     * Offene, zur Zahlung freigegebene Eingangsrechnungen mit Vorschlagswerten.
     *
     * @return Collection<int, array{invoice: IncomingEInvoice, supplier: Supplier|null, iban: string|null, bic: string|null, amount: float, gross: float, discount_percent: float|null, execute_on: CarbonImmutable, uses_discount: bool, blocked: string|null}>
     */
    public function proposals(?CarbonImmutable $today = null): Collection {
        $today = $today ?? CarbonImmutable::today();

        return IncomingEInvoice::query()
            ->where('status', IncomingEInvoice::STATUS_PAYMENT_RELEASED)
            ->whereNull('paid_in_run_id')
            ->orderBy('due_date')
            ->get()
            ->map(fn (IncomingEInvoice $invoice): array => $this->proposalFor($invoice, $today))
            ->values();
    }

    /**
     * @return array{invoice: IncomingEInvoice, supplier: Supplier|null, iban: string|null, bic: string|null, amount: float, gross: float, discount_percent: float|null, execute_on: CarbonImmutable, uses_discount: bool, blocked: string|null}
     */
    public function proposalFor(IncomingEInvoice $invoice, ?CarbonImmutable $today = null): array {
        $today = $today ?? CarbonImmutable::today();
        $supplier = $this->supplierFor($invoice);
        [$iban, $bic] = $this->bankDetails($invoice, $supplier);

        $gross = round((float) ($invoice->amount_gross?->toFloat() ?? 0.0), 2);
        $discountDate = $this->discountDate($invoice);
        $usesDiscount = $discountDate !== null
            && $discountDate->greaterThanOrEqualTo($today)
            && (float) ($invoice->discount_percent ?? 0) > 0;

        $amount = $usesDiscount
            ? round($gross * (1 - ((float) $invoice->discount_percent / 100)), 2)
            : $gross;

        // Skontotermin schlägt Nettotermin — er ist der teurere, wenn man ihn
        // verpasst. Ohne Skonto wird zum Fälligkeitstag gezahlt, nie früher.
        $executeOn = $usesDiscount
            ? $discountDate
            : $this->dueDate($invoice, $today);

        return [
            'invoice' => $invoice,
            'supplier' => $supplier,
            'iban' => $iban,
            'bic' => $bic,
            'amount' => $amount,
            'gross' => $gross,
            'discount_percent' => $usesDiscount ? (float) $invoice->discount_percent : null,
            'execute_on' => $executeOn->lessThan($today) ? $today : $executeOn,
            'uses_discount' => $usesDiscount,
            'blocked' => $this->blockingReason($iban, $amount),
        ];
    }

    /**
     * Warum diese Position (noch) nicht zahlbar ist. Ohne IBAN gibt es keine
     * Überweisung — das darf nicht erst die Bank feststellen.
     */
    private function blockingReason(?string $iban, float $amount): ?string {
        if ($iban === null || $iban === '') {
            return 'missing_iban';
        }
        if ($amount <= 0) {
            return 'zero_amount';
        }

        return null;
    }

    /** @return array{0: string|null, 1: string|null} */
    private function bankDetails(IncomingEInvoice $invoice, ?Supplier $supplier): array {
        // Die Rechnung gewinnt vor dem Stammsatz: Sie ist der jüngere Beleg,
        // und eine geänderte Bankverbindung steht dort zuerst.
        $iban = trim((string) ($invoice->creditor_iban ?? ''));
        $bic = trim((string) ($invoice->creditor_bic ?? ''));

        if ($iban === '' && $supplier !== null) {
            $account = $supplier->primaryBankAccount();
            $iban = trim((string) ($account->iban ?? ''));
            $bic = trim((string) ($account->bic ?? ''));
        }

        return [$iban === '' ? null : $iban, $bic === '' ? null : $bic];
    }

    private function supplierFor(IncomingEInvoice $invoice): ?Supplier {
        $name = trim((string) ($invoice->seller_name ?? ''));
        if ($name === '') {
            return null;
        }

        return Supplier::query()->where('name', $name)->first();
    }

    /** Skontotermin = Rechnungsdatum + Skontotage. */
    private function discountDate(IncomingEInvoice $invoice): ?CarbonImmutable {
        $days = (int) ($invoice->discount_days ?? 0);
        if ($days <= 0 || $invoice->issue_date === null) {
            return null;
        }

        return CarbonImmutable::parse($invoice->issue_date)->addDays($days);
    }

    private function dueDate(IncomingEInvoice $invoice, CarbonImmutable $today): CarbonImmutable {
        return $invoice->due_date !== null
            ? CarbonImmutable::parse($invoice->due_date)
            : $today;
    }
}
