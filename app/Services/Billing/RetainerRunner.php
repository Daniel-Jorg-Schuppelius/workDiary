<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerRunner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\BillingAgreementMode;
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\{Invoice, Organization};
use App\Support\Tz;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Monatslauf des Retainer-Modus (Feature 098): erzeugt+pusht je aktivem
 * Retainer-Agreement einer Organisation die Vormonats-Pauschale an Lexoffice.
 * Idempotent über customer_billing_statements.retainer_invoice_id.
 */
class RetainerRunner {
    public function __construct(private readonly RetainerLexofficeService $service) {}

    public function runFor(CustomerBillingAgreement $agreement, int $year, int $month): ?Invoice {
        if (! $agreement->isRetainerMode()) {
            return null;
        }

        return $this->service->pushMonthlyRetainer($agreement, $year, $month);
    }

    /** @return array{created: int, skipped: int, failed: int} */
    public function runDueForOrganization(Organization $organization, ?CarbonInterface $now = null): array {
        $now = $now !== null ? Carbon::parse($now) : Carbon::now(Tz::current());
        $previous = $now->copy()->setTimezone(Tz::current())->startOfMonth()->subMonthNoOverflow();

        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0];

        $agreements = CustomerBillingAgreement::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->where('mode', BillingAgreementMode::Retainer->value)
            ->get();

        foreach ($agreements as $agreement) {
            $already = $agreement->statements()
                ->where('year', $previous->year)
                ->where('month', $previous->month)
                ->whereNotNull('retainer_invoice_id')
                ->exists();
            if ($already) {
                $result['skipped']++;

                continue;
            }

            try {
                $this->service->pushMonthlyRetainer($agreement, $previous->year, $previous->month);
                $result['created']++;
            } catch (ValidationException) {
                // z. B. fehlender Pauschalbetrag — nicht fatal, nächster Lauf/Button.
                $result['skipped']++;
            } catch (\Throwable) {
                // Lexoffice down/5xx/429/Contact — Marker bleibt NULL, Retry möglich.
                $result['failed']++;
            }
        }

        return $result;
    }
}
