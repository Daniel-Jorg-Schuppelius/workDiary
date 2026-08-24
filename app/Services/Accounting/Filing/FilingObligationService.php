<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingObligationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Enums\Finance\{FilingObligationKind, FilingObligationStatus, VatFilingInterval};
use App\Models\Accounting\{AccountingFilingObligation, AccountingProfile};
use App\Models\{Organization, User};
use App\Services\Accounting\VatFilingProfileResolver;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;

/**
 * Steuerliche Meldepflichten eines Jahres (Feature 125, MVP-686).
 *
 * Die Zeile in `accounting_filing_obligations` ist die **Erledigungsspur**,
 * nicht der Terminkalender: `due_on` wird bei jedem Abgleich neu aus
 * Meldeprofil, Periode und Feiertagskalender berechnet. Ändert sich das
 * Intervall, verschieben sich die Termine — die Erledigung bleibt.
 */
class FilingObligationService {
    public function __construct(
        private readonly VatFilingPeriodService $periods,
        private readonly FilingDeadlineCalculator $deadlines,
        private readonly VatFilingProfileResolver $profile,
        private readonly RecapitulativeStatementService $recapitulative,
    ) {}

    /**
     * Pflichten eines Kalenderjahres anlegen bzw. Fristen auffrischen.
     *
     * @return array{created: int, updated: int}
     */
    public function syncYear(Organization $organization, int $year): array {
        $created = 0;
        $updated = 0;

        foreach ($this->expectedFor($organization, $year) as $expected) {
            $existing = AccountingFilingObligation::query()
                ->where('organization_id', $organization->id)
                ->where('kind', $expected['kind']->value)
                ->where('period_key', $expected['period_key'])
                ->first();

            if ($existing instanceof AccountingFilingObligation) {
                if (! $existing->due_on->isSameDay($expected['due_on'])) {
                    $existing->update(['due_on' => $expected['due_on']->toDateString()]);
                    $updated++;
                }

                continue;
            }

            AccountingFilingObligation::query()->create([
                'organization_id' => $organization->id,
                'kind' => $expected['kind'],
                'period_key' => $expected['period_key'],
                'due_on' => $expected['due_on']->toDateString(),
                'status' => FilingObligationStatus::Open,
            ]);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Welche Pflichten das Jahr fachlich vorsieht.
     *
     * @return list<array{kind: FilingObligationKind, period_key: string, due_on: CarbonImmutable}>
     */
    public function expectedFor(Organization $organization, int $year): array {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();
        if (! $profile instanceof AccountingProfile || ! $profile->isLocalActive()) {
            // Ohne lokale Buchhaltung führt jemand anders die Pflichten.
            return [];
        }

        $expected = [];

        foreach ($this->periods->periodsFor($organization, $year) as $period) {
            if ($period->interval === VatFilingInterval::Annual) {
                continue;
            }

            $expected[] = [
                'kind' => FilingObligationKind::VatAdvance,
                'period_key' => $period->key,
                'due_on' => $this->deadlines->vatAdvance($organization, $period),
            ];
        }

        // Zusammenfassende Meldung: eigener Zeitraum nach § 18a UStG, sobald
        // es überhaupt innergemeinschaftliche Lieferungen gibt.
        foreach ($this->recapitulativePeriods($organization, $year) as $period) {
            $expected[] = [
                'kind' => FilingObligationKind::Recapitulative,
                'period_key' => 'ZM-' . $period->key,
                'due_on' => $this->deadlines->recapitulative($period),
            ];
        }

        // Sondervorauszahlung nur bei Monatsmeldung und erfasster Verlängerung.
        if ($this->profile->at($organization, CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 2, 10)))->requiresSpecialPrepayment()
            && $this->profile->hasExtension($organization, CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 2, 10)))) {
            $expected[] = [
                'kind' => FilingObligationKind::SpecialPrepayment,
                'period_key' => sprintf('%d-SVZ', $year),
                'due_on' => $this->deadlines->specialPrepayment($year),
            ];
        }

        // Die Jahreserklärung gibt es immer, auch bei Befreiung von der
        // Voranmeldung (§ 18 Abs. 3 UStG).
        if ($this->profile->at($organization, CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 12, 31))) !== VatFilingInterval::None) {
            $expected[] = [
                'kind' => FilingObligationKind::AnnualReturn,
                'period_key' => sprintf('%d-J', $year),
                'due_on' => $this->deadlines->annualReturn($year, $this->taxAdvised()),
            ];
        }

        return $expected;
    }

    /**
     * Meldezeiträume der Zusammenfassenden Meldung eines Jahres.
     *
     * Nur Zeiträume mit Umsatz erzeugen eine Pflicht — ohne
     * innergemeinschaftliche Lieferungen gibt es nichts zu melden
     * (§ 18a Abs. 1 UStG).
     *
     * @return list<VatReturnPeriod>
     */
    private function recapitulativePeriods(Organization $organization, int $year): array {
        $periods = [];
        $quarter = 1;

        while ($quarter <= 4) {
            $reference = CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, ($quarter - 1) * 3 + 1));
            $interval = $this->recapitulative->intervalFor($organization, $reference->addMonths(2)->endOfMonth());

            if ($interval === VatFilingInterval::Monthly) {
                for ($month = ($quarter - 1) * 3 + 1; $month <= $quarter * 3; $month++) {
                    $period = VatReturnPeriod::make(VatFilingInterval::Monthly, $year, $month);
                    if (! NumberHelper::isZeroPrecise($this->recapitulative->totalFor($organization, $period->from, $period->to))) {
                        $periods[] = $period;
                    }
                }
            } else {
                $period = VatReturnPeriod::make(VatFilingInterval::Quarterly, $year, $quarter);
                if (! NumberHelper::isZeroPrecise($this->recapitulative->totalFor($organization, $period->from, $period->to))) {
                    $periods[] = $period;
                }
            }

            $quarter++;
        }

        return $periods;
    }

    /**
     * Pflichten im Zeitraum, offene zuerst.
     *
     * @return Collection<int, AccountingFilingObligation>
     */
    public function inRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): Collection {
        return AccountingFilingObligation::query()
            ->where('organization_id', $organization->id)
            ->whereDate('due_on', '>=', $from->toDateString())
            ->whereDate('due_on', '<=', $to->toDateString())
            ->orderBy('due_on')
            ->orderBy('kind')
            ->get();
    }

    /**
     * Überfällige, noch offene Pflichten.
     *
     * @return Collection<int, AccountingFilingObligation>
     */
    public function overdue(Organization $organization, ?CarbonImmutable $asOf = null): Collection {
        $day = ($asOf ?? CarbonImmutable::now())->startOfDay();

        return AccountingFilingObligation::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->whereDate('due_on', '<', $day->toDateString())
            ->orderBy('due_on')
            ->get();
    }

    /** Erledigung festhalten — abgegeben oder bewusst nicht erforderlich. */
    public function mark(
        AccountingFilingObligation $obligation,
        FilingObligationStatus $status,
        User $actor,
        ?string $note = null,
    ): AccountingFilingObligation {
        $obligation->update([
            'status' => $status,
            'submitted_at' => $status === FilingObligationStatus::Submitted ? now() : null,
            'note' => $note,
            'actor_user_id' => $actor->id,
        ]);

        return $obligation->refresh();
    }

    /**
     * Steuerlich beraten? Verlängert die Frist der Jahreserklärung
     * (§ 149 Abs. 3 AO) — eine Angabe der Organisation, keine Ableitung.
     */
    public function taxAdvised(): bool {
        return (bool) Setting::get('finance.accounting_tax_advised', false);
    }
}
