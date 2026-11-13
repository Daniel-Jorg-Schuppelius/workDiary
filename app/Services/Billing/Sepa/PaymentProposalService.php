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

namespace App\Services\Billing\Sepa;

use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Accounting\AccountingOpenItem;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\GiroCode\GiroCodeCheck;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use App\Support\Crypto\BlindIndex;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{BankHelper, NumberHelper};
use Illuminate\Support\Collection;

/**
 * Zahlungsvorschlag (Feature 120, MVP-609).
 *
 * Vorschlag heißt Vorschlag: Hier entsteht nichts Buchhalterisches, sondern
 * eine Liste mit dem wirtschaftlichsten Ausführungsdatum je Rechnung. Ob und
 * was gezahlt wird, entscheidet der Mensch im nächsten Schritt.
 */
class PaymentProposalService {
    public function __construct(
        private readonly IncomingRetentionService $retentions,
        private readonly GiroCodeCheck $giroCodes,
    ) {}

    /**
     * Offene, zur Zahlung freigegebene Eingangsrechnungen mit Vorschlagswerten.
     *
     * @return Collection<int, array{invoice: IncomingEInvoice, supplier: Supplier|null, iban: string|null, bic: string|null, amount: float, gross: float, retained: float, discount_percent: float|null, execute_on: CarbonImmutable, uses_discount: bool, blocked: string|null}>
     */
    public function proposals(?CarbonImmutable $today = null): Collection {
        $today = $today ?? CarbonImmutable::today();

        return IncomingEInvoice::query()
            ->purchases()
            ->where('status', IncomingEInvoiceStatus::PaymentReleased)
            ->whereNull('paid_in_run_id')
            ->orderBy('due_date')
            ->get()
            ->map(fn (IncomingEInvoice $invoice): array => $this->proposalFor($invoice, $today))
            ->values();
    }

    /**
     * @return array{invoice: IncomingEInvoice, supplier: Supplier|null, iban: string|null, bic: string|null, amount: float, gross: float, retained: float, discount_percent: float|null, execute_on: CarbonImmutable, uses_discount: bool, blocked: string|null}
     */
    public function proposalFor(IncomingEInvoice $invoice, ?CarbonImmutable $today = null): array {
        $today = $today ?? CarbonImmutable::today();
        $supplier = $this->supplierFor($invoice);
        [$iban, $bic] = $this->bankDetails($invoice, $supplier);

        $grossMoney = $invoice->amount_gross;
        $gross = round($grossMoney?->toFloat() ?? 0.0, 2);
        $discountDate = $this->discountDate($invoice);
        $usesDiscount = $discountDate !== null
            && $discountDate->greaterThanOrEqualTo($today)
            && (float) ($invoice->discount_percent ?? 0) > 0;

        // Wie Invoice::skontoAmount(): der Skontobetrag wird exakt gerechnet,
        // gerundet und abgezogen — Überweisung und Skontobuchung passen zueinander.
        $amount = $usesDiscount && $grossMoney !== null
            ? $grossMoney->minusPercentage(NumberHelper::normalizeDecimalString((string) $invoice->discount_percent))->toFloat()
            : $gross;
        // Einbehalte (MVP-953) mindern die Zahlung; Skonto gilt auf den vollen Betrag.
        $retained = (float) $this->retentions->retainedAmount($invoice);
        $amount = round($amount - $retained, 2);

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
            'retained' => $retained,
            'discount_percent' => $usesDiscount ? (float) $invoice->discount_percent : null,
            'execute_on' => $executeOn->lessThan($today) ? $today : $executeOn,
            'uses_discount' => $usesDiscount,
            'blocked' => $this->blockingReason($invoice, $supplier, $iban, $amount),
        ];
    }

    /**
     * Warum diese Position (noch) nicht zahlbar ist. Ohne IBAN gibt es keine
     * Überweisung — das darf nicht erst die Bank feststellen. Und eine
     * Rechnungs-IBAN, die vom Stammsatz abweicht, ist ein klassischer
     * Rechnungsbetrugs-Vektor (Vollscan 2026-08-23, E3): Blocker, bis ein
     * Berechtigter die Abweichung auditiert bestätigt hat.
     */
    private function blockingReason(IncomingEInvoice $invoice, ?Supplier $supplier, ?string $iban, float $amount): ?string {
        if ($iban === null || $iban === '') {
            return 'missing_iban';
        }
        if ($amount <= 0) {
            return 'zero_amount';
        }
        if ($this->ibanDiffersFromMaster($invoice, $supplier)) {
            return 'iban_differs';
        }
        $hold = $this->paymentHold($invoice, $supplier);
        if ($hold !== null) {
            return $hold;
        }
        // Strittige Verbindlichkeit (MVP-1559): gezahlt wird erst, wenn das Kennzeichen am offenen Posten zurückgenommen ist.
        $disputed = AccountingOpenItem::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->getKey())
            ->disputed()
            ->exists();
        if ($disputed) {
            return 'disputed';
        }

        return null;
    }

    /**
     * Rechnungs-IBAN passt zu keiner hinterlegten Bankverbindung des
     * Lieferanten und ist noch nicht bestätigt. Alle Bankverbindungen zählen
     * (MVP-1108), nicht nur die primäre.
     */
    public function ibanDiffersFromMaster(IncomingEInvoice $invoice, ?Supplier $supplier): bool {
        $invoiceIban = BankHelper::normalizeIBAN(trim((string) ($invoice->creditor_iban ?? '')));
        if ($invoiceIban === null || $supplier === null || self::confirmedFor($invoice, $invoiceIban)) {
            return false;
        }
        // Der Sammellieferant (MVP-1109) hat keine Bankverbindung: jede Rechnungs-IBAN ist zu bestätigen.
        if ($supplier->is_collective) {
            return true;
        }
        $known = $supplier->bankAccounts()->withoutGlobalScopes()->whereNotNull('iban_hash')->pluck('iban_hash')->all();

        return $known !== [] && array_intersect($known, BlindIndex::ibanCandidates($invoiceIban)) === [];
    }

    /**
     * Die Bestätigung einer abweichenden Rechnungs-IBAN gilt nur für genau
     * die bestätigte IBAN (Sicherheitsprüfung Feature 208, M4): jede spätere
     * Änderung sperrt wieder. Abdruck als geschlüsselter Blind-Index.
     */
    public static function confirmedFor(IncomingEInvoice $invoice, ?string $iban): bool {
        $hash = $invoice->creditor_iban_confirmed_hash;

        return $invoice->creditor_iban_confirmed_at !== null
            && is_string($hash) && $hash !== ''
            && in_array($hash, BlindIndex::ibanCandidates($iban), true);
    }

    /** GiroCode-IBAN weicht ab und ist nicht bestätigt (MVP-1663) — sperrt Vorschlag und Freigabe des Laufs. */
    public function giroCodeUnconfirmed(IncomingEInvoice $invoice): bool {
        return $this->giroCodes->requiresConfirmation($invoice, $this->supplierFor($invoice));
    }

    /**
     * Gründe, die eine Zahlung zur Rechnung anhalten — auch die Auszahlung
     * ihrer Einbehalte (Feature 208, N1): GiroCode-Abweichung ohne
     * Bestätigung oder unentschiedene Sammel-PDF.
     */
    public function paymentHold(IncomingEInvoice $invoice, ?Supplier $supplier = null): ?string {
        $supplier ??= $this->supplierFor($invoice);
        // GiroCode (MVP-1663): IBAN im Code ≠ Rechnungs-IBAN bzw. Lieferanten-Bankverbindung, oder neu — bis zur Bestätigung.
        if ($this->giroCodes->requiresConfirmation($invoice, $supplier)) {
            return 'girocode_differs';
        }
        // Sammel-PDF (MVP-1662): unentschiedene Grenzen bzw. aufgeteilt — gezahlt werden die Teile.
        if (IncomingInvoiceSplitter::isHolding($invoice) || $invoice->isSplitBundle()) {
            return 'bundle_unresolved';
        }

        return null;
    }

    /**
     * Abdruck der Lage, die jemand im Zahlungsvorschlag bestätigt (N2):
     * Rechnungs-IBAN, GiroCode-IBAN, Lieferant. Geht als verstecktes Feld
     * mit und wird unter Sperre verglichen — ändert sich dazwischen etwas,
     * gilt die Bestätigung nicht.
     */
    public function confirmationToken(IncomingEInvoice $invoice): string {
        $reading = (array) $invoice->girocode;

        return BlindIndex::of(implode('|', [
            BankHelper::normalizeIBAN(trim((string) ($invoice->creditor_iban ?? ''))) ?? '',
            BankHelper::normalizeIBAN(is_string($reading['iban'] ?? null) ? $reading['iban'] : null) ?? '',
            (string) $this->supplierFor($invoice)?->id,
            (string) $invoice->id,
        ]));
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

    /** Lieferant, den der Zahlungsvorschlag für die Rechnung annimmt (für Bestätigung und Gegenprobe). */
    public function supplierFor(IncomingEInvoice $invoice): ?Supplier {
        // Feste Zuordnung (MVP-1108) zuerst; die Heuristik bleibt für Altbestand.
        if ($invoice->supplier_id !== null) {
            return $invoice->supplier;
        }
        // USt-ID vor Name (Vollscan 2026-08-23, E3): sie ist das stabilere
        // Merkmal — der Name kommt frei formatiert aus der Rechnung.
        $vatId = trim((string) ($invoice->seller_vat_id ?? ''));
        if ($vatId !== '') {
            $byVat = Supplier::query()->whereIdentifier('vat_id', $vatId)->first();
            if ($byVat !== null) {
                return $byVat;
            }
        }

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
