<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountInvoiceRunner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\BillingAgreementMode;
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Invoice;
use App\Services\Finance\BillingModeLockedException;
use App\Services\Invoicing\InvoiceGenerator;
use App\Support\Tz;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Rechnungs-Modus der Kunden-Sonderkonditionen (Feature 098): fakturiert den
 * Vormonat eines invoice-Mode-Agreements über die unveränderte
 * InvoiceGenerator-Pipeline — die Sonderkonditions-Sätze stecken dank des
 * RateCalculator-Hooks bereits in den rate-Snapshots der Einträge.
 * Idempotent über das exported-Flag (leerer Folgelauf ⇒ skipped).
 */
class AccountInvoiceRunner {
    public function __construct(private readonly InvoiceGenerator $generator) {}

    public function runFor(CustomerBillingAgreement $agreement, int $year, int $month): ?Invoice {
        if (! $agreement->active || $agreement->mode !== BillingAgreementMode::Invoice) {
            return null;
        }

        $customer = $agreement->customer()->firstOrFail();
        $start = Carbon::parse(sprintf('%04d-%02d-01', $year, $month), Tz::current())->startOfDay();

        return $this->generator->fromTimeEntries($customer, null, [
            'from' => $start->toDateString(),
            'to' => $start->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /** @return array{created: int, skipped: int, failed: int} */
    public function runDue(?CarbonInterface $now = null): array {
        $now = $now !== null ? Carbon::instance(Carbon::parse($now)) : Carbon::now(Tz::current());
        $previous = $now->copy()->setTimezone(Tz::current())->startOfMonth()->subMonthNoOverflow();

        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0];

        $agreements = CustomerBillingAgreement::query()
            ->where('active', true)
            ->where('mode', BillingAgreementMode::Invoice->value)
            ->get();

        foreach ($agreements as $agreement) {
            try {
                $invoice = $this->runFor($agreement, $previous->year, $previous->month);
                $invoice !== null ? $result['created']++ : $result['skipped']++;
            } catch (ValidationException) {
                // Keine offenen abrechenbaren Zeiten im Vormonat — bereits
                // fakturiert oder leerer Monat.
                $result['skipped']++;
            } catch (BillingModeLockedException) {
                // Externe Rechnungshoheit (BillingMode) — kein lokaler Beleg.
                $result['failed']++;
            }
        }

        return $result;
    }
}
