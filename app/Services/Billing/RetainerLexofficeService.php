<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerLexofficeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Models\Billing\CustomerBillingAgreement;
use App\Models\{Customer, ExternalReference, Invoice};
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Plugins\PluginManager;
use App\Services\Invoicing\InvoiceGenerator;
use App\Support\Tz;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Retainer-Modus (Feature 098): erzeugt die feste Monatspauschale bzw. die
 * periodische Spitzabrechnung als NORMALE Lexoffice-Rechnung und übergibt sie
 * finalisiert an Lexoffice (Lexoffice führt Beleg + Zahlung). Der lokale
 * Leistungssaldo bleibt in {@see CustomerAccountStatementService}; der
 * Lexoffice-Zahlstatus fließt über {@see RetainerVoucherReconciler} zurück.
 *
 * Idempotenz der Pauschale: 1:1 Monat↔Beleg über
 * customer_billing_statements.retainer_invoice_id (unter Sperre gesetzt).
 */
class RetainerLexofficeService {
    public function __construct(
        private readonly InvoiceGenerator $generator,
        private readonly LexofficeInvoiceService $lexoffice,
        private readonly CustomerAccountStatementService $statements,
    ) {}

    /**
     * Erzeugt + pusht die Monatspauschale eines Retainer-Agreements. Idempotent:
     * existiert bereits ein verknüpfter Beleg für den Monat, wird er zurückgegeben.
     */
    public function pushMonthlyRetainer(CustomerBillingAgreement $agreement, int $year, int $month): ?Invoice {
        if (! $agreement->isRetainerMode()) {
            return null;
        }
        $this->assertConfigured();

        $customer = $agreement->customer()->firstOrFail();
        $amount = (float) ($agreement->expected_monthly_amount ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_amount_required')]);
        }

        $statement = $this->statements->ensure($agreement, $year, $month);

        return DB::transaction(function () use ($agreement, $customer, $statement, $amount, $year, $month): Invoice {
            // Sperre gegen parallele Doppel-Erzeugung; danach Marker erneut prüfen.
            $locked = $statement->newQuery()->lockForUpdate()->findOrFail($statement->id);
            if ($locked->retainer_invoice_id !== null) {
                return Invoice::query()->findOrFail($locked->retainer_invoice_id);
            }

            $serviceDate = $this->monthEnd($year, $month);
            $description = (string) __('customer-billing.retainer_line', ['period' => $locked->periodLabel()]);
            $placeholder = \sprintf('LEX-R-%d-%04d%02d', $agreement->id, $year, $month);

            $invoice = $this->generator->retainerChargeFor(
                $customer,
                $description,
                (string) $amount,
                $placeholder,
                Invoice::TYPE_RETAINER,
                $serviceDate,
            );

            // Push nach Lexoffice (finalize=true → Lexoffice-Nummer + status=issued);
            // schlägt der Push fehl, rollt die Transaktion den Draft zurück.
            $this->lexoffice->publish($invoice, $this->resolveContactExternalId($customer), finalize: true);

            $locked->update(['retainer_invoice_id' => $invoice->id]);

            return $invoice->refresh();
        });
    }

    /**
     * Spitz-/Ausgleichsrechnung über den aktuell offenen Leistungssaldo als
     * normale Lexoffice-Rechnung. Kein Doppel-Lauf, solange eine unbezahlte
     * Spitzabrechnung offen ist.
     */
    public function pushTrueUp(CustomerBillingAgreement $agreement, ?CarbonInterface $cutoff = null): Invoice {
        if (! $agreement->isRetainerMode()) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.retainer_only')]);
        }
        $this->assertConfigured();

        $this->statements->recalculateOpen($agreement);
        $balance = $this->openBalance($agreement);
        if ($balance <= 0.005) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.trueup_no_open_balance')]);
        }

        if ($this->hasOpenTrueUp($agreement)) {
            throw ValidationException::withMessages(['agreement' => __('customer-billing.trueup_already_open')]);
        }

        $customer = $agreement->customer()->firstOrFail();
        $now = $cutoff !== null ? Carbon::parse($cutoff) : Carbon::now(Tz::current());
        $placeholder = \sprintf('LEX-TU-%d-%s', $agreement->id, $now->format('YmdHis'));
        $description = (string) __('customer-billing.trueup_line', ['date' => $now->translatedFormat('d.m.Y')]);

        return DB::transaction(function () use ($customer, $balance, $placeholder, $description, $now): Invoice {
            // TYPE_RETAINER wie die Pauschale: beide sind extern (Lexoffice)
            // geführte Konto-Belege, fallen daher aus lokalem Umsatzreport/DATEV.
            $invoice = $this->generator->retainerChargeFor(
                $customer,
                $description,
                (string) round($balance, 2),
                $placeholder,
                Invoice::TYPE_RETAINER,
                $now,
            );
            $this->lexoffice->publish($invoice, $this->resolveContactExternalId($customer), finalize: true);

            return $invoice->refresh();
        });
    }

    private function assertConfigured(): void {
        if (! $this->lexoffice->isConfigured()) {
            throw ValidationException::withMessages(['lexoffice' => __('customer-billing.lexoffice_not_configured')]);
        }
    }

    private function resolveContactExternalId(Customer $customer): string {
        $ref = ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();

        if ($ref !== null) {
            return $ref->external_id;
        }

        // Fallback: Kontakt erst in Lexoffice anlegen (wie der Bestandspfad).
        $plugin = app(PluginManager::class)->get(LexofficePlugin::ID);
        if ($plugin instanceof LexofficePlugin) {
            return $plugin->pushContact($customer);
        }

        throw new RuntimeException('Lexoffice-Kontakt für Retainer-Push nicht auflösbar.');
    }

    /** Aktuell offener Saldo = balance des jüngsten Statements der Kette. */
    private function openBalance(CustomerBillingAgreement $agreement): float {
        $latest = $agreement->statements()
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        return $latest !== null ? (float) $latest->balance : 0.0;
    }

    /**
     * Offene (unbezahlte) Spitzabrechnung dieses Kunden — verhindert einen
     * zweiten Ausgleich vor der Zahlung. Eine Spitzabrechnung ist ein
     * TYPE_RETAINER-Beleg, der NICHT als Monatspauschale eines Statements
     * verlinkt ist.
     */
    private function hasOpenTrueUp(CustomerBillingAgreement $agreement): bool {
        $customer = $agreement->customer()->firstOrFail();
        $pauschaleIds = $agreement->statements()
            ->whereNotNull('retainer_invoice_id')
            ->pluck('retainer_invoice_id')
            ->all();

        return Invoice::query()
            ->where('organization_id', $agreement->organization_id)
            ->where('customer_id', $customer->id)
            ->where('type', Invoice::TYPE_RETAINER)
            ->whereNotIn('id', $pauschaleIds === [] ? [0] : $pauschaleIds)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
            ->exists();
    }

    private function monthEnd(int $year, int $month): CarbonInterface {
        return Carbon::parse(\sprintf('%04d-%02d-01', $year, $month), Tz::current())->endOfMonth();
    }
}
