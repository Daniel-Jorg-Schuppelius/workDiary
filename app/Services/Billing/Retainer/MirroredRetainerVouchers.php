<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredRetainerVouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Retainer;

use App\Enums\Billing\{AccountPaymentSource, BillingAgreementMode, DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingStatement};
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Services\Billing\{BillingModeResolver, CustomerAccountStatementService, RetainerVoucherRef};
use App\Services\Billing\Contracts\RetainerVoucherLinks;
use App\Support\Tz;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zahlstand der Pauschalbelege aus dem Belegspiegel `accounting_vouchers`
 * (Feature 098, MVP-1505). Zwei Zuordnungswege wie bei Lexoffice: die aus
 * workDiary ausgestellte Pauschale (Verknüpfung des Issuers an der Rechnung)
 * und ein im Programm erstellter Beleg, der an einem Monat hängt
 * (ExternalReference {@see EXT_TYPE}).
 *
 * Gebucht wird netto, anteilig nach bezahltem Brutto: der Spiegel führt den
 * offenen Betrag brutto, der Leistungssaldo rechnet mit Nettosätzen.
 */
abstract class MirroredRetainerVouchers implements RetainerVoucherLinks {
    public const string EXT_TYPE = 'retainer_voucher';

    public function __construct(private readonly CustomerAccountStatementService $statements) {}

    abstract public function pluginId(): string;

    abstract protected function label(): string;

    abstract protected function paymentSource(): AccountPaymentSource;

    /** `external_type`, unter dem der Issuer des Programms die ausgestellte Rechnung verknüpft. */
    abstract protected function invoiceExtType(): string;

    /** Spiegel-ID eines vom Issuer angelegten Belegs. */
    protected function mirrorId(string $remoteId): string {
        return $remoteId;
    }

    public static function linkOf(CustomerBillingStatement $statement, string $pluginId): ?ExternalReference {
        return ExternalReference::query()
            ->forPlugin((int) $statement->organization_id, $pluginId, self::EXT_TYPE)
            ->forReferenceable($statement)
            ->first();
    }

    public function reconcile(Organization $organization): array {
        $result = ['booked' => 0, 'revoked' => 0, 'skipped' => 0, 'linked' => $this->autoLink($organization)];

        $tracked = $this->trackedVouchers($organization);
        $mirrored = $this->mirrored($organization, array_column($tracked, 0))->keyBy('external_id');
        foreach ($tracked as [$mirrorId, $agreement, $statement, $invoice]) {
            $voucher = $mirrored->get($mirrorId);
            if (! $voucher instanceof AccountingVoucher || $agreement === null || ! $agreement->isRetainerMode()) {
                $result['skipped']++;

                continue;
            }

            if ($voucher->voucher_state === AccountingVoucherState::Cancelled || $voucher->source_deleted_at !== null) {
                $this->statements->revokeExternalPayment($agreement, $this->paymentSource(), $mirrorId);
                if ($invoice !== null) {
                    $this->markInvoice($invoice, InvoiceStatus::Cancelled);
                }
                $result['revoked']++;

                continue;
            }

            $total = $this->money($voucher->total_amount, $voucher) ?? Money::zero($this->currency($voucher));
            $open = $this->money($voucher->open_amount, $voucher)
                ?? ($voucher->voucher_state === AccountingVoucherState::Paid ? Money::zero($total->getCurrency()) : $total);
            $paidGross = $total->minus($open);
            if (! $paidGross->isPositive()) {
                $result['skipped']++;

                continue;
            }

            $paidOn = Carbon::parse($voucher->paid_date ?? $voucher->voucher_date ?? Carbon::now(Tz::current()), Tz::current());
            $this->statements->bookExternalPayment(
                $agreement,
                $this->paymentSource(),
                $mirrorId,
                $this->paidNet($voucher, $paidGross, $total),
                $paidOn,
                (string) __('customer-billing.channel_payment_note', ['number' => (string) $voucher->voucher_number, 'system' => $this->label()]),
                $statement,
            );
            if ($invoice !== null) {
                $this->markInvoice($invoice, $open->isPositive() ? InvoiceStatus::PartiallyPaid : InvoiceStatus::Paid, $paidOn);
            }
            $result['booked']++;
        }

        return $result;
    }

    /**
     * Belegfreie Monate mit einer im Programm geführten Rechnung verknüpfen —
     * bewusst eng: Rechnung im Monat, Nettobetrag gleich der Pauschale, genau
     * ein Kandidat. Alles andere bleibt für die Handverknüpfung liegen.
     */
    public function autoLink(Organization $organization): int {
        $linked = 0;
        foreach ($this->retainerAgreements($organization) as $agreement) {
            $expected = $agreement->expected_monthly_amount;
            if ($expected === null || ! $expected->isPositive()) {
                continue;
            }
            $taken = array_keys($this->linkedExternalIds($organization));
            $open = $agreement->statements()
                ->whereNull('retainer_invoice_id')
                ->when($taken !== [], static fn ($q) => $q->whereNotIn('id', $taken))
                ->orderBy('year')->orderBy('month')
                ->get();
            $candidates = $open->isEmpty() ? collect() : $this->candidates($organization, (int) $agreement->customer_id);
            foreach ($open as $statement) {
                $start = $statement->periodStart();
                $inMonth = $candidates->filter(static fn (AccountingVoucher $v): bool => $v->voucher_date !== null && $v->voucher_date->betweenIncluded($start, $start->copy()->endOfMonth()));
                $match = $inMonth->count() === 1 ? $inMonth->first() : null;
                $net = $match instanceof AccountingVoucher ? $this->money($match->net_amount, $match) : null;
                if ($net === null || $net->minus(Money::of($expected->getAmount(), $net->getCurrency()))->abs()->toFloat() >= 0.005) {
                    continue;
                }
                $this->attach($statement, $match);
                $candidates = $candidates->reject(static fn (AccountingVoucher $v): bool => $v->id === $match->id);
                $linked++;
            }
        }

        return $linked;
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
            ->forPlugin($organizationId, $this->pluginId(), self::EXT_TYPE)
            ->where('referenceable_type', (new CustomerBillingStatement)->getMorphClass())
            ->whereIn('referenceable_id', $ids)
            ->get(['referenceable_id', 'external_id']);
        $vouchers = AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $this->pluginId())
            ->whereIn('external_id', $refs->pluck('external_id'))
            ->get()
            ->keyBy('external_id');

        $linked = [];
        foreach ($refs as $ref) {
            $voucher = $vouchers->get($ref->external_id);
            $linked[(int) $ref->referenceable_id] = $voucher instanceof AccountingVoucher
                ? $this->toRef($voucher)
                : new RetainerVoucherRef(externalId: (string) $ref->external_id, key: '');
        }

        return $linked;
    }

    public function linkableVouchers(Customer $customer, CustomerBillingStatement $statement): array {
        $organization = $customer->organization()->firstOrFail();

        return array_values($this->candidates($organization, (int) $customer->id, (int) $statement->id)
            ->map(fn (AccountingVoucher $voucher): RetainerVoucherRef => $this->toRef($voucher))
            ->all());
    }

    public function link(CustomerBillingStatement $statement, string $key): RetainerVoucherRef {
        $voucher = (new AccountingVoucher)->resolveRouteBinding($key);
        $organization = Organization::query()->find($statement->organization_id);
        $customerId = (int) $statement->agreement()->value('customer_id');
        $allowed = $voucher instanceof AccountingVoucher && $organization !== null
            && $this->candidates($organization, $customerId, (int) $statement->id)->contains('id', $voucher->id);
        if (! $allowed) {
            throw ValidationException::withMessages(['voucher' => __('customer-billing.voucher_not_found')]);
        }
        $this->attach($statement, $voucher);

        return $this->toRef($voucher);
    }

    public function unlink(CustomerBillingStatement $statement): void {
        $ref = self::linkOf($statement, $this->pluginId());
        if ($ref === null) {
            return;
        }
        $ref->delete();

        $agreement = $statement->agreement()->first();
        if ($agreement !== null) {
            $this->statements->revokeExternalPayment($agreement, $this->paymentSource(), (string) $ref->external_id);
        }
    }

    /** Ein Monat trägt höchstens einen Beleg, ein Beleg höchstens einen Monat. */
    private function attach(CustomerBillingStatement $statement, AccountingVoucher $voucher): void {
        DB::transaction(function () use ($statement, $voucher): void {
            ExternalReference::query()
                ->forPlugin((int) $statement->organization_id, $this->pluginId(), self::EXT_TYPE)
                ->forReferenceable($statement)
                ->where('external_id', '!=', $voucher->external_id)
                ->delete();
            ExternalReference::link((int) $statement->organization_id, $this->pluginId(), self::EXT_TYPE, $statement, (string) $voucher->external_id);
        });
    }

    /**
     * Gespiegelte Kundenrechnungen, die weder Entwurf noch storniert sind und
     * an keinem anderen Monat hängen; aus workDiary ausgestellte Pauschalen
     * haben ihren Monat schon.
     *
     * @return Collection<int, AccountingVoucher>
     */
    private function candidates(Organization $organization, int $customerId, ?int $keepStatementId = null): Collection {
        $taken = array_values(array_filter(
            $this->linkedExternalIds($organization),
            static fn (int $statementId): bool => $statementId !== $keepStatementId,
            ARRAY_FILTER_USE_KEY,
        ));
        $pushed = array_map(strval(...), array_keys($this->pushedInvoices($organization)));

        return AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('customer_id', $customerId)
            ->where('direction', DocumentDirection::Outgoing->value)
            ->where('document_kind', DocumentKind::Invoice->value)
            ->whereIn('voucher_state', [AccountingVoucherState::Open->value, AccountingVoucherState::Paid->value])
            ->where('archived', false)
            ->whereNull('source_deleted_at')
            ->when([...$taken, ...$pushed] !== [], static fn ($q) => $q->whereNotIn('external_id', [...$taken, ...$pushed]))
            ->orderByDesc('voucher_date')
            ->get();
    }

    /**
     * Belege, deren Zahlung in einen Leistungssaldo fließt.
     *
     * @return list<array{0: string, 1: ?CustomerBillingAgreement, 2: ?CustomerBillingStatement, 3: ?Invoice}>
     */
    private function trackedVouchers(Organization $organization): array {
        $tracked = [];
        $byInvoice = CustomerBillingStatement::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('retainer_invoice_id')
            ->get()
            ->keyBy('retainer_invoice_id');
        foreach ($this->pushedInvoices($organization) as $mirrorId => $invoice) {
            // Numerische Beleg-IDs werden als Array-Schlüssel zu int.
            $tracked[] = [(string) $mirrorId, CustomerBillingAgreement::query()->where('customer_id', $invoice->customer_id)->first(), $byInvoice->get($invoice->id), $invoice];
        }

        $links = $this->linkedExternalIds($organization);
        $statements = $links === [] ? collect() : CustomerBillingStatement::query()->where('organization_id', $organization->id)->whereKey(array_keys($links))->with('agreement')->get();
        foreach ($statements as $statement) {
            $tracked[] = [$links[(int) $statement->id], $statement->agreement, $statement, null];
        }

        return $tracked;
    }

    /** @return array<array-key, Invoice> Spiegel-ID → aus workDiary ausgestellte Pauschale */
    private function pushedInvoices(Organization $organization): array {
        $refs = ExternalReference::query()
            ->forPlugin($organization->id, $this->pluginId(), $this->invoiceExtType())
            ->where('referenceable_type', (new Invoice)->getMorphClass())
            ->get(['external_id', 'referenceable_id']);
        if ($refs->isEmpty()) {
            return [];
        }
        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $refs->pluck('referenceable_id'))
            ->where('type', Invoice::TYPE_RETAINER)
            ->get()
            ->keyBy('id');

        $map = [];
        foreach ($refs as $ref) {
            $invoice = $invoices->get($ref->referenceable_id);
            if ($invoice instanceof Invoice) {
                $map[$this->mirrorId((string) $ref->external_id)] = $invoice;
            }
        }

        return $map;
    }

    /** @return array<int, string> Monats-ID → Spiegel-ID */
    private function linkedExternalIds(Organization $organization): array {
        return ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), self::EXT_TYPE)
            ->where('referenceable_type', (new CustomerBillingStatement)->getMorphClass())
            ->pluck('external_id', 'referenceable_id')
            ->mapWithKeys(static fn ($externalId, $statementId): array => [(int) $statementId => (string) $externalId])
            ->all();
    }

    /**
     * Aktive Pauschal-Vereinbarungen, deren Kunde dieses Programm führt.
     *
     * @return Collection<int, CustomerBillingAgreement>
     */
    private function retainerAgreements(Organization $organization): Collection {
        $modes = app(BillingModeResolver::class);

        return CustomerBillingAgreement::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->where('mode', BillingAgreementMode::Retainer->value)
            ->with('customer')
            ->get()
            ->filter(fn (CustomerBillingAgreement $agreement): bool => $agreement->customer !== null && $modes->effectiveFor($agreement->customer)->value === $this->pluginId())
            ->values();
    }

    /**
     * @param  list<string>  $externalIds
     * @return Collection<int, AccountingVoucher>
     */
    private function mirrored(Organization $organization, array $externalIds): Collection {
        return AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->whereIn('external_id', $externalIds)
            ->get();
    }

    private function paidNet(AccountingVoucher $voucher, Money $paidGross, Money $total): Money {
        $net = $this->money($voucher->net_amount, $voucher);
        if ($net === null || ! $total->isPositive()) {
            return $paidGross;
        }

        return $paidGross->compareTo($total) >= 0 ? $net : $net->times($paidGross->getAmount())->dividedBy($total->getAmount());
    }

    private function toRef(AccountingVoucher $voucher): RetainerVoucherRef {
        $open = $this->money($voucher->open_amount, $voucher);

        return new RetainerVoucherRef(
            externalId: (string) $voucher->external_id,
            key: (string) $voucher->sqid,
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            net: $this->money($voucher->net_amount, $voucher),
            gross: $this->money($voucher->total_amount, $voucher),
            settled: $voucher->voucher_state === AccountingVoucherState::Paid || ($open !== null && ! $open->isPositive()),
        );
    }

    private function money(?string $amount, AccountingVoucher $voucher): ?Money {
        return Money::ofNullable($amount, $this->currency($voucher));
    }

    private function currency(AccountingVoucher $voucher): CurrencyCode {
        return CurrencyCode::tryFrom((string) $voucher->currency) ?? CurrencyCode::Euro;
    }

    /** Pflegt nur Status und Zahldatum der Pauschalrechnung. */
    private function markInvoice(Invoice $invoice, InvoiceStatus $status, ?Carbon $paidOn = null): void {
        if ($invoice->status === $status) {
            return;
        }
        $invoice->status = $status;
        if ($status === InvoiceStatus::Paid && $paidOn !== null) {
            $invoice->paid_on = $paidOn;
        }
        $invoice->saveQuietly();
    }
}
