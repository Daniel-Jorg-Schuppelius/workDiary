<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerVoucherReconciler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\BillingAgreementMode;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingStatement};
use App\Models\{ExternalReference, Invoice, LexofficeVoucher, Organization};
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin, LexofficeVoucherNetAmount};
use App\Support\Billing\VoucherTypes;
use App\Support\Tz;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Retainer-Zahlstatus-Rücksync (Feature 098): spiegelt den Lexoffice-Beleg-
 * status (paid/teilbezahlt/storniert) der Pauschal-/Ausgleichsbelege zurück in
 * den Leistungssaldo. Läuft nach {@see \App\Plugins\Lexoffice\LexofficeVoucherSync}.
 * Idempotent über die Voucher-UUID (source_reference).
 *
 * Zwei Zuordnungswege, weil die Pauschale aus beiden Richtungen entstehen kann:
 *   1. workDiary hat gepusht → ExternalReference → lokale TYPE_RETAINER-Invoice
 *   2. Beleg wurde direkt in Lexoffice erstellt → customer_billing_statements
 *      .lexoffice_voucher_id (per {@see autoLink()} oder manuell verknüpft)
 *
 * Gebucht wird NETTO: die voucherlist liefert Brutto, der Leistungssaldo
 * rechnet mit Nettosätzen — ohne Umrechnung wäre jede Zahlung um die USt zu hoch.
 */
class RetainerVoucherReconciler {
    /** Belegarten, die als Kundenrechnung für eine Pauschale in Frage kommen. */
    private const INVOICE_TYPES = VoucherTypes::SALES_INVOICES;

    public function __construct(
        private readonly CustomerAccountStatementService $statements,
        private readonly LexofficeVoucherNetAmount $netAmounts,
    ) {}

    /** @return array{booked: int, revoked: int, skipped: int, linked: int} */
    public function reconcile(Organization $organization): array {
        $result = ['booked' => 0, 'revoked' => 0, 'skipped' => 0, 'linked' => $this->autoLink($organization)];

        $vouchers = LexofficeVoucher::query()
            ->where('organization_id', $organization->id)
            ->where('archived', false)
            ->whereNotNull('customer_id')
            ->get();

        $invoiceByExternalId = $this->retainerInvoiceMap($organization);
        $statementByVoucherId = $this->linkedStatementMap($organization);
        $statementByInvoiceId = $this->invoiceStatementMap($organization);

        foreach ($vouchers as $voucher) {
            $invoice = $invoiceByExternalId[$voucher->external_id] ?? null;
            // Monat des Belegs — egal ob selbst gepusht (Invoice) oder in
            // Lexoffice erstellt und verknüpft (Voucher).
            $statement = $statementByVoucherId[$voucher->id]
                ?? ($invoice !== null ? $statementByInvoiceId[$invoice->id] ?? null : null);
            $agreement = $invoice !== null
                ? $this->retainerAgreementFor((int) $invoice->customer_id)
                : $statement?->agreement()->first();

            if ($agreement === null || ! $agreement->isRetainerMode()) {
                $result['skipped']++;

                continue;
            }

            $status = (string) $voucher->voucher_status;
            if ($status === 'voided') {
                $this->statements->revokeLexofficePayment($agreement, $voucher->external_id);
                if ($invoice !== null) {
                    $this->markInvoice($invoice, Invoice::STATUS_CANCELLED);
                }
                $result['revoked']++;

                continue;
            }

            $total = $voucher->total_amount ?? Money::zero($voucher->currency);
            $open = $voucher->open_amount ?? $total;
            $paidGross = $total->minus($open);

            if (! $paidGross->isPositive()) {
                $result['skipped']++;

                continue;
            }

            $paidOn = $voucher->paid_date ?? $voucher->voucher_date;
            $paidOn = $paidOn !== null
                ? Carbon::parse($paidOn, Tz::current())
                : Carbon::now(Tz::current());

            $this->statements->bookLexofficePayment(
                $agreement,
                $voucher->external_id,
                $this->netAmounts->paidNet($voucher, $paidGross, $total),
                $paidOn,
                (string) __('customer-billing.lexoffice_payment_note', ['number' => (string) $voucher->voucher_number]),
                $statement,
            );

            if ($invoice !== null) {
                $this->markInvoice($invoice, $open->isPositive() ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_PAID, $paidOn);
            }
            $result['booked']++;
        }

        return $result;
    }

    /**
     * Verknüpft belegfreie Retainer-Monate mit einer bereits in Lexoffice
     * geführten Rechnung. Bewusst eng gefasst — falsch zugeordnetes Geld ist
     * teurer als eine Handverknüpfung: Kundenrechnung im Monat des Statements,
     * Nettobetrag exakt gleich der vereinbarten Pauschale, und genau EIN
     * Kandidat. Alles andere bleibt für die manuelle Zuordnung liegen.
     */
    public function autoLink(Organization $organization): int {
        $linked = 0;

        foreach ($this->retainerAgreements($organization) as $agreement) {
            $expected = $agreement->expected_monthly_amount;
            if ($expected === null || ! $expected->isPositive()) {
                continue;
            }

            $open = $agreement->statements()
                ->whereNull('retainer_invoice_id')
                ->whereNull('lexoffice_voucher_id')
                ->orderBy('year')->orderBy('month')
                ->get();
            if ($open->isEmpty()) {
                continue;
            }

            $candidates = $this->linkableVouchers($organization, (int) $agreement->customer_id);
            foreach ($open as $statement) {
                $match = $this->matchFor($statement, $candidates, $expected);
                if ($match === null) {
                    continue;
                }

                $statement->update(['lexoffice_voucher_id' => $match->id]);
                $candidates = $candidates->reject(fn (LexofficeVoucher $v): bool => $v->id === $match->id);
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * Manuelle Zuordnung eines Belegs zu einem Monat (Gegenstück zum Auto-Match).
     * Der bisherige Beleg des Monats wird gelöst; die Zahlung selbst zieht der
     * nächste Reconcile-Lauf nach.
     */
    public function link(CustomerBillingStatement $statement, LexofficeVoucher $voucher): void {
        $statement->update(['lexoffice_voucher_id' => $voucher->id]);
    }

    /** Löst die Verknüpfung und nimmt die daraus gebuchte Zahlung zurück. */
    public function unlink(CustomerBillingStatement $statement): void {
        $voucher = $statement->lexofficeVoucher()->first();
        $statement->update(['lexoffice_voucher_id' => null]);

        $agreement = $statement->agreement()->first();
        if ($voucher !== null && $agreement !== null) {
            $this->statements->revokeLexofficePayment($agreement, $voucher->external_id);
        }
    }

    /**
     * Zuordenbare Belege eines Kunden: Kundenrechnungen, die weder Entwurf noch
     * storniert sind und noch an keinem Monat hängen.
     *
     * @return \Illuminate\Support\Collection<int, LexofficeVoucher>
     */
    public function linkableVouchers(Organization $organization, int $customerId, ?int $keepStatementId = null): \Illuminate\Support\Collection {
        $taken = CustomerBillingStatement::query()
            ->whereNotNull('lexoffice_voucher_id')
            ->when($keepStatementId !== null, fn ($q) => $q->where('id', '!=', $keepStatementId))
            ->pluck('lexoffice_voucher_id')
            ->all();

        return LexofficeVoucher::query()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customerId)
            ->where('archived', false)
            ->whereIn('voucher_type', self::INVOICE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->when($taken !== [], fn ($q) => $q->whereNotIn('id', $taken))
            ->orderByDesc('voucher_date')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LexofficeVoucher>  $candidates
     */
    private function matchFor(CustomerBillingStatement $statement, \Illuminate\Support\Collection $candidates, Money $expectedNet): ?LexofficeVoucher {
        $start = $statement->periodStart();
        $end = $start->copy()->endOfMonth();

        $inMonth = $candidates->filter(function (LexofficeVoucher $voucher) use ($start, $end): bool {
            $date = $voucher->voucher_date;

            return $date !== null && $date->betweenIncluded($start, $end);
        });

        if ($inMonth->count() !== 1) {
            return null; // kein oder mehrdeutiger Kandidat → Handverknüpfung
        }

        /** @var LexofficeVoucher $voucher */
        $voucher = $inMonth->first();
        $net = $this->netAmounts->for($voucher);

        return $net !== null && $net->minus($this->sameCurrency($expectedNet, $net))->abs()->toFloat() < 0.005
            ? $voucher
            : null;
    }

    /** Beträge stammen aus zwei Tabellen ohne gemeinsame Währungsspalte. */
    private function sameCurrency(Money $value, Money $reference): Money {
        return $value->getCurrency() === $reference->getCurrency()
            ? $value
            : Money::of($value->getAmount(), $reference->getCurrency());
    }

    /** @return \Illuminate\Support\Collection<int, CustomerBillingAgreement> */
    private function retainerAgreements(Organization $organization): \Illuminate\Support\Collection {
        return CustomerBillingAgreement::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->where('mode', BillingAgreementMode::Retainer->value)
            ->get();
    }

    private function retainerAgreementFor(int $customerId): ?CustomerBillingAgreement {
        return CustomerBillingAgreement::query()
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * @return array<int, CustomerBillingStatement> Voucher-ID → verknüpfter Monat.
     */
    private function linkedStatementMap(Organization $organization): array {
        return CustomerBillingStatement::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('lexoffice_voucher_id')
            ->get()
            ->keyBy('lexoffice_voucher_id')
            ->all();
    }

    /**
     * @return array<int, CustomerBillingStatement> Invoice-ID → Monat der gepushten Pauschale.
     */
    private function invoiceStatementMap(Organization $organization): array {
        return CustomerBillingStatement::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('retainer_invoice_id')
            ->get()
            ->keyBy('retainer_invoice_id')
            ->all();
    }

    /**
     * @return array<string, Invoice> Lexoffice-UUID → lokale Retainer-Invoice.
     */
    private function retainerInvoiceMap(Organization $organization): array {
        $refs = ExternalReference::query()
            ->forPlugin($organization->id, LexofficePlugin::ID, LexofficeInvoiceService::EXT_TYPE_INVOICE)
            ->where('referenceable_type', (new Invoice)->getMorphClass())
            ->get(['external_id', 'referenceable_id']);

        if ($refs->isEmpty()) {
            return [];
        }

        $invoices = Invoice::query()
            ->whereIn('id', $refs->pluck('referenceable_id'))
            ->where('type', Invoice::TYPE_RETAINER)
            ->get()
            ->keyBy('id');

        $map = [];
        foreach ($refs as $ref) {
            $invoice = $invoices->get($ref->referenceable_id);
            if ($invoice !== null) {
                $map[$ref->external_id] = $invoice;
            }
        }

        return $map;
    }

    /** Pflegt ausschließlich Whitelist-Felder (status, paid_on) der Invoice. */
    private function markInvoice(Invoice $invoice, string $status, ?Carbon $paidOn = null): void {
        if ($invoice->status === $status) {
            return;
        }
        $invoice->status = $status;
        if ($status === Invoice::STATUS_PAID && $paidOn !== null) {
            $invoice->paid_on = $paidOn;
        }
        $invoice->saveQuietly();
    }
}
