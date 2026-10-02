<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRetainerVouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services\Retainer;

use App\Enums\Billing\{AccountPaymentSource, BillingAgreementMode};
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingStatement};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin, LexofficeVoucherNetAmount};
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\VoucherTypes;
use App\Services\Billing\Contracts\RetainerVoucherLinks;
use App\Services\Billing\{CustomerAccountStatementService, RetainerVoucherRef};
use App\Support\Tz;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retainer-Zahlstatus-Rücksync (Feature 098): spiegelt den Lexoffice-Beleg-
 * status (paid/teilbezahlt/storniert) der Pauschal-/Ausgleichsbelege zurück in
 * den Leistungssaldo. Läuft nach {@see \App\Plugins\Lexoffice\LexofficeVoucherSync}.
 * Idempotent über die Voucher-UUID (source_reference).
 *
 * Zwei Zuordnungswege, weil die Pauschale aus beiden Richtungen entstehen kann:
 *   1. workDiary hat gepusht → ExternalReference → lokale TYPE_RETAINER-Invoice
 *   2. Beleg wurde direkt in Lexoffice erstellt → ExternalReference Monat →
 *      Beleg-ID (Typ {@see EXT_TYPE}, per {@see autoLink()} oder manuell; MVP-1027)
 *
 * Gebucht wird NETTO: die voucherlist liefert Brutto, der Leistungssaldo
 * rechnet mit Nettosätzen — ohne Umrechnung wäre jede Zahlung um die USt zu hoch.
 */
class LexofficeRetainerVouchers implements RetainerVoucherLinks {
    /** Belegarten, die als Kundenrechnung für eine Pauschale in Frage kommen. */
    private const INVOICE_TYPES = VoucherTypes::SALES_INVOICES;

    public const EXT_TYPE = 'retainer_voucher';

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
            $statement = $statementByVoucherId[$voucher->external_id]
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
                $this->statements->revokeExternalPayment($agreement, AccountPaymentSource::Lexoffice, $voucher->external_id);
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

            $this->statements->bookExternalPayment(
                $agreement,
                AccountPaymentSource::Lexoffice,
                $voucher->external_id,
                $this->netAmounts->paidNet($voucher, $paidGross, $total),
                $paidOn,
                (string) __('lexoffice::customer-billing.channel_payment_note', ['number' => (string) $voucher->voucher_number, 'system' => 'Lexoffice']),
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

            $linkedIds = array_keys($this->linkedExternalIds($organization));
            $open = $agreement->statements()
                ->whereNull('retainer_invoice_id')
                ->when($linkedIds !== [], fn ($q) => $q->whereNotIn('id', $linkedIds))
                ->orderBy('year')->orderBy('month')
                ->get();
            if ($open->isEmpty()) {
                continue;
            }

            $candidates = $this->candidates($organization, (int) $agreement->customer_id);
            foreach ($open as $statement) {
                $match = $this->matchFor($statement, $candidates, $expected);
                if ($match === null) {
                    continue;
                }

                $this->attach($statement, $match);
                $candidates = $candidates->reject(fn (LexofficeVoucher $v): bool => $v->id === $match->id);
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * Bestehende Verknüpfung eines Monats (MVP-1027: Referenz statt Spalte).
     */
    public static function linkOf(CustomerBillingStatement $statement): ?ExternalReference {
        return ExternalReference::query()
            ->forPlugin((int) $statement->organization_id, LexofficePlugin::ID, self::EXT_TYPE)
            ->forReferenceable($statement)
            ->first();
    }

    public function linkedVouchers(iterable $statements): array {
        $ids = [];
        $organizationId = null;
        foreach ($statements as $statement) {
            $ids[] = (int) $statement->id;
            $organizationId ??= (int) $statement->organization_id;
        }
        if ($ids === []) {
            return [];
        }

        $refs = ExternalReference::query()
            ->forPlugin($organizationId, LexofficePlugin::ID, self::EXT_TYPE)
            ->where('referenceable_type', (new CustomerBillingStatement)->getMorphClass())
            ->whereIn('referenceable_id', $ids)
            ->get(['referenceable_id', 'external_id']);
        $vouchers = LexofficeVoucher::query()
            ->where('organization_id', $organizationId)
            ->whereIn('external_id', $refs->pluck('external_id'))
            ->get()
            ->keyBy('external_id');

        $linked = [];
        foreach ($refs as $ref) {
            $voucher = $vouchers->get($ref->external_id);
            $linked[(int) $ref->referenceable_id] = $voucher instanceof LexofficeVoucher
                ? $this->toRef($voucher)
                : new RetainerVoucherRef(externalId: (string) $ref->external_id, key: '');
        }

        return $linked;
    }

    public function linkableVouchers(Customer $customer, CustomerBillingStatement $statement): array {
        $organization = $customer->organization()->firstOrFail();

        $refs = [];
        foreach ($this->candidates($organization, (int) $customer->id, (int) $statement->id) as $voucher) {
            $refs[] = $this->toRef($voucher);
        }

        return $refs;
    }

    /**
     * Manuelle Zuordnung eines Belegs zu einem Monat (Gegenstück zum Auto-Match).
     * Der bisherige Beleg des Monats wird gelöst; die Zahlung selbst zieht der
     * nächste Reconcile-Lauf nach.
     */
    public function link(CustomerBillingStatement $statement, string $key): RetainerVoucherRef {
        $voucher = (new LexofficeVoucher)->resolveRouteBinding($key);
        $organization = Organization::query()->find($statement->organization_id);
        $customerId = (int) $statement->agreement()->value('customer_id');
        $allowed = $voucher instanceof LexofficeVoucher && $organization !== null
            && $this->candidates($organization, $customerId, (int) $statement->id)->contains('id', $voucher->id);
        if (! $allowed) {
            throw ValidationException::withMessages(['voucher' => __('lexoffice::customer-billing.voucher_not_found')]);
        }

        $this->attach($statement, $voucher);

        return $this->toRef($voucher);
    }

    /** Löst die Verknüpfung und nimmt die daraus gebuchte Zahlung zurück. */
    public function unlink(CustomerBillingStatement $statement): void {
        $ref = self::linkOf($statement);
        if ($ref === null) {
            return;
        }
        $ref->delete();

        $agreement = $statement->agreement()->first();
        if ($agreement !== null) {
            $this->statements->revokeExternalPayment($agreement, AccountPaymentSource::Lexoffice, (string) $ref->external_id);
        }
    }

    /**
     * Hängt den Beleg an den Monat. Ein Monat trägt höchstens einen Beleg
     * (Index `extref_unique`), ein Beleg höchstens einen Monat (Schlüssel der
     * Referenz ist die Beleg-ID).
     */
    public function attach(CustomerBillingStatement $statement, LexofficeVoucher $voucher): void {
        DB::transaction(function () use ($statement, $voucher): void {
            ExternalReference::query()
                ->forPlugin((int) $statement->organization_id, LexofficePlugin::ID, self::EXT_TYPE)
                ->forReferenceable($statement)
                ->where('external_id', '!=', $voucher->external_id)
                ->delete();
            ExternalReference::link((int) $statement->organization_id, LexofficePlugin::ID, self::EXT_TYPE, $statement, (string) $voucher->external_id);
        });
    }

    /**
     * Zuordenbare Belege eines Kunden: Kundenrechnungen, die weder Entwurf noch
     * storniert sind und noch an keinem Monat hängen.
     *
     * @return Collection<int, LexofficeVoucher>
     */
    private function candidates(Organization $organization, int $customerId, ?int $keepStatementId = null): Collection {
        $taken = array_values(array_filter(
            $this->linkedExternalIds($organization),
            static fn (int $statementId): bool => $statementId !== $keepStatementId,
            ARRAY_FILTER_USE_KEY,
        ));

        return LexofficeVoucher::query()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customerId)
            ->where('archived', false)
            ->whereIn('voucher_type', self::INVOICE_TYPES)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->when($taken !== [], fn ($q) => $q->whereNotIn('external_id', $taken))
            ->orderByDesc('voucher_date')
            ->get();
    }

    private function toRef(LexofficeVoucher $voucher): RetainerVoucherRef {
        return new RetainerVoucherRef(
            externalId: (string) $voucher->external_id,
            key: (string) $voucher->sqid,
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            net: $voucher->net_amount,
            gross: $voucher->total_amount,
            settled: ! ($voucher->open_amount?->isPositive() ?? true),
        );
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
     * @return array<string, CustomerBillingStatement> Beleg-ID in Lexoffice → verknüpfter Monat.
     */
    private function linkedStatementMap(Organization $organization): array {
        $links = $this->linkedExternalIds($organization);
        if ($links === []) {
            return [];
        }
        $map = [];
        foreach (CustomerBillingStatement::query()->where('organization_id', $organization->id)->whereKey(array_keys($links))->get() as $statement) {
            $map[$links[(int) $statement->id]] = $statement;
        }

        return $map;
    }

    /** @return array<int, string> Monats-ID → Beleg-ID in Lexoffice */
    private function linkedExternalIds(Organization $organization): array {
        return ExternalReference::query()
            ->forPlugin($organization, LexofficePlugin::ID, self::EXT_TYPE)
            ->where('referenceable_type', (new CustomerBillingStatement)->getMorphClass())
            ->pluck('external_id', 'referenceable_id')
            ->mapWithKeys(static fn ($externalId, $statementId): array => [(int) $statementId => (string) $externalId])
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
