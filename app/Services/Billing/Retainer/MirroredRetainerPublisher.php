<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredRetainerPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Retainer;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Numbering\NumberScope;
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Plugins\Support\PluginOrgContext;
use App\Services\Billing\Contracts\RetainerPublisher;
use App\Services\Billing\CustomerAccountStatementService;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use App\Services\Numbering\Contracts\DocumentIssuer;
use App\Support\Tz;
use Carbon\CarbonInterface;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Pauschal-Modus (Feature 098, MVP-1505) für Programme, die ihre Rechnungen
 * über einen {@see DocumentIssuer} ausstellen und deren Zahlstand im
 * Belegspiegel `accounting_vouchers` liegt (sevDesk, easybill).
 *
 * Anders als bei Lexoffice läuft die Übergabe nicht in einer DB-Transaktion:
 * das Programm legt den Beleg in mehreren Schritten an (anlegen, dann
 * fertigstellen bzw. versenden), und die Verknüpfung des Issuers muss einen
 * Abbruch dazwischen überleben. Ein abgebrochener Versuch bleibt als Entwurf
 * mit fester Platzhalternummer liegen und wird beim nächsten Versuch
 * fortgesetzt, statt einen zweiten Beleg anzulegen.
 */
abstract class MirroredRetainerPublisher implements RetainerPublisher {
    public function __construct(
        private readonly InvoiceGenerator $generator,
        private readonly CustomerAccountStatementService $statements,
        private readonly InvoiceIssueService $issuing,
    ) {}

    abstract public function pluginId(): string;

    abstract public function label(): string;

    /** Plugin eingeschaltet und Zugang hinterlegt; ohne Spiegel kein Zahlstand. */
    abstract protected function configuredFor(int $organizationId): bool;

    abstract protected function issuer(): DocumentIssuer;

    /** Präfix der Platzhalternummer, bis das Programm nummeriert. */
    abstract protected function numberPrefix(): string;

    public function isConfigured(): bool {
        $organizationId = PluginOrgContext::currentId();

        return $organizationId !== null && $this->configuredFor($organizationId);
    }

    public function pushMonthlyRetainer(CustomerBillingAgreement $agreement, int $year, int $month): ?Invoice {
        if (! $agreement->isRetainerMode()) {
            return null;
        }
        $this->assertConfigured($agreement);

        $amount = $agreement->expected_monthly_amount;
        if ($amount === null || ! $amount->isPositive()) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_amount_required')]);
        }
        $customer = $agreement->customer()->firstOrFail();
        $statement = $this->statements->ensure($agreement, $year, $month);

        return Cache::lock('retainer-push:' . $statement->id, 120)->block(30, function () use ($agreement, $customer, $statement, $amount, $year, $month): Invoice {
            $statement->refresh();
            if ($statement->retainer_invoice_id !== null) {
                return Invoice::query()->findOrFail($statement->retainer_invoice_id);
            }
            if (MirroredRetainerVouchers::linkOf($statement, $this->pluginId()) !== null) {
                throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_voucher_already_linked', ['system' => $this->label()])]);
            }

            $placeholder = \sprintf('%s-R-%d-%04d%02d', $this->numberPrefix(), $agreement->id, $year, $month);
            $invoice = $this->pendingDraft($customer, $placeholder, exact: true)
                ?? $this->generator->retainerChargeFor(
                    $customer,
                    (string) __('customer-billing.retainer_line', ['period' => $statement->periodLabel()]),
                    $amount->getAmount(),
                    $placeholder,
                    Invoice::TYPE_RETAINER,
                    Carbon::parse(\sprintf('%04d-%02d-01', $year, $month), Tz::current())->endOfMonth(),
                );
            $this->issue($invoice);
            $statement->update(['retainer_invoice_id' => $invoice->id]);

            return $invoice->refresh();
        });
    }

    public function pushTrueUp(CustomerBillingAgreement $agreement, ?CarbonInterface $cutoff = null): Invoice {
        if (! $agreement->isRetainerMode()) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_only')]);
        }
        $this->assertConfigured($agreement);

        $this->statements->recalculateOpen($agreement);
        $balance = $agreement->statements()->orderByDesc('year')->orderByDesc('month')->first()->balance
            ?? Money::zero($agreement->currency);
        if (! $balance->isPositive()) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.trueup_no_open_balance')]);
        }
        if ($this->hasOpenTrueUp($agreement)) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.trueup_already_open')]);
        }

        $customer = $agreement->customer()->firstOrFail();
        $now = $cutoff !== null ? Carbon::parse($cutoff) : Carbon::now(Tz::current());
        $prefix = \sprintf('%s-TU-%d-', $this->numberPrefix(), $agreement->id);

        return Cache::lock('retainer-trueup:' . $agreement->id, 120)->block(30, function () use ($customer, $balance, $prefix, $now): Invoice {
            // TYPE_RETAINER wie die Pauschale: extern geführt, fällt aus lokalem Umsatzreport und DATEV.
            $invoice = $this->pendingDraft($customer, $prefix, exact: false)
                ?? $this->generator->retainerChargeFor(
                    $customer,
                    (string) __('customer-billing.trueup_line', ['date' => $now->translatedFormat('d.m.Y')]),
                    $balance->getAmount(),
                    $prefix . $now->format('YmdHis'),
                    Invoice::TYPE_RETAINER,
                    $now,
                );
            $this->issue($invoice);

            return $invoice->refresh();
        });
    }

    /** Erst das Programm (nummeriert und führt den Beleg), dann der lokale Rechnungsstatus. */
    private function issue(Invoice $invoice): void {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return;
        }
        $number = $this->issuer()->issue($invoice, NumberScope::Invoice)->number;
        $this->issuing->issue($invoice, ['number' => $number]);
    }

    /** Entwurf eines abgebrochenen Versuchs: er trägt schon die Verknüpfung zum Beleg im Programm. */
    private function pendingDraft(Customer $customer, string $number, bool $exact): ?Invoice {
        return Invoice::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('type', Invoice::TYPE_RETAINER)
            ->where('status', InvoiceStatus::Draft)
            ->when($exact, static fn ($q) => $q->where('number', $number), static fn ($q) => $q->whereLikeEscaped('number', $number, 'prefix'))
            ->orderBy('id')
            ->first();
    }

    /** Unbezahlte Spitzabrechnung: ein Pauschalbeleg, der an keinem Monat hängt. */
    private function hasOpenTrueUp(CustomerBillingAgreement $agreement): bool {
        $monthly = $agreement->statements()->whereNotNull('retainer_invoice_id')->pluck('retainer_invoice_id')->all();

        return Invoice::query()
            ->where('organization_id', $agreement->organization_id)
            ->where('customer_id', $agreement->customer_id)
            ->where('type', Invoice::TYPE_RETAINER)
            ->when($monthly !== [], static fn ($q) => $q->whereNotIn('id', $monthly))
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->exists();
    }

    private function assertConfigured(CustomerBillingAgreement $agreement): void {
        if (! $this->configuredFor((int) $agreement->organization_id)) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.channel_not_configured', ['system' => $this->label()])]);
        }
    }
}
