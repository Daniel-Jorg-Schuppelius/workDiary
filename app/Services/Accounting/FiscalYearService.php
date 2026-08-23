<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FiscalYearService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\AccountingPeriodStatus;
use App\Models\Accounting\{AccountingFiscalYear, AccountingPeriod};
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Geschäftsjahre und ihre Perioden (Feature 125, MVP-671).
 *
 * Perioden entstehen ausschließlich zusammen mit dem Jahr: ein Jahr mit
 * Lücken zwischen den Perioden hätte Buchungsdaten, die nirgendwo hingehören.
 * Der Abschlussworkflow (soft/hard close, Wiedereröffnung) folgt mit MVP-677 —
 * hier wird nur die Struktur erzeugt, gegen die MVP-672 prüft.
 */
class FiscalYearService {
    /** Monatsperioden; abweichende Rhythmen (Quartal) sind bewusst nicht im ersten Schnitt. */
    public const PERIOD_LENGTH_MONTHS = 1;

    /**
     * Legt ein Geschäftsjahr ab `$startsOn` mit zwölf Monatsperioden an.
     *
     * @throws ValidationException bei Überschneidung mit einem bestehenden Jahr
     */
    public function create(Organization $organization, CarbonImmutable $startsOn, ?string $label = null): AccountingFiscalYear {
        $start = $startsOn->startOfDay();
        $end = $start->addYear()->subDay();
        $label ??= $this->deriveLabel($start, $end);

        $overlap = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->first();

        if ($overlap instanceof AccountingFiscalYear) {
            throw ValidationException::withMessages([
                'starts_on' => (string) __('accounting.ledger.error.fiscal_year_overlap', ['year' => $overlap->label]),
            ]);
        }

        return DB::transaction(function () use ($organization, $start, $end, $label): AccountingFiscalYear {
            $year = AccountingFiscalYear::query()->create([
                'organization_id' => $organization->id,
                'label' => $label,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'status' => AccountingPeriodStatus::Open,
            ]);

            $cursor = $start;
            $sequence = 1;
            while ($cursor->lessThanOrEqualTo($end)) {
                $periodEnd = $cursor->addMonths(self::PERIOD_LENGTH_MONTHS)->subDay();
                if ($periodEnd->greaterThan($end)) {
                    $periodEnd = $end;
                }

                AccountingPeriod::query()->create([
                    'organization_id' => $organization->id,
                    'accounting_fiscal_year_id' => $year->id,
                    'sequence' => $sequence,
                    'starts_on' => $cursor->toDateString(),
                    'ends_on' => $periodEnd->toDateString(),
                    'status' => AccountingPeriodStatus::Open,
                ]);

                $cursor = $periodEnd->addDay();
                $sequence++;
            }

            $year->audit('accounting.fiscal_year_created', [
                'label' => $year->label,
                'starts_on' => $year->starts_on->toDateString(),
                'ends_on' => $year->ends_on->toDateString(),
                'periods' => $sequence - 1,
            ]);

            return $year->fresh(['periods']) ?? $year;
        });
    }

    /** Periode, in die ein Buchungsdatum fällt (Guard-Grundlage ab MVP-672). */
    public function periodFor(Organization $organization, CarbonImmutable $date): ?AccountingPeriod {
        return AccountingPeriod::query()
            ->where('organization_id', $organization->id)
            ->covering($date)
            ->first();
    }

    /**
     * „2026" bei deckungsgleichem Kalenderjahr, sonst „2026/2027" — das
     * abweichende Geschäftsjahr ist der Regelfall, den Beschriftungen sonst
     * falsch darstellen.
     */
    private function deriveLabel(CarbonImmutable $start, CarbonImmutable $end): string {
        return $start->year === $end->year ? (string) $start->year : $start->year . '/' . $end->year;
    }
}
