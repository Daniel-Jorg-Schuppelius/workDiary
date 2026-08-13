<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeForecastService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Surcharge;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\{ScheduledShift, User};
use App\Models\Surcharge\SurchargeRule;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Zuschlags-/Lohnarten-Prognose (MVP-533, Q1-Drittabgleich): bewertet
 * GEPLANTE Dienste mit denselben Regeln wie die Ist-Abrechnung
 * ({@see SurchargeRule::matchesContext} + {@see SurchargeCalculator}) und
 * aggregiert die voraussichtlichen Zuschlagsminuten je Monat und Lohnart.
 *
 * Reine Vorschau — KEINE Persistenz, keine `time_rule_results`; die
 * Ist-Bewertung bleibt allein bei der {@see TimeRuleEngine}. Kontext je
 * Dienst: Teams des Users + Schichttyp; ein Standort existiert vor dem
 * Stempeln nicht (standortbedingte Regeln greifen in der Prognose nicht).
 */
class SurchargeForecastService {
    public function __construct(private readonly SurchargeCalculator $calculator) {}

    /**
     * @return array{
     *     months: list<string>,
     *     rows: list<array{wage_type_code: string, label: string, minutes: array<string, int>, total: int}>,
     *     totals: array<string, int>,
     * }
     */
    public function forecast(int $organizationId, CarbonImmutable $from, int $months = 3, ?int $userId = null): array {
        $months = max(1, min(12, $months));
        $start = $from->startOfMonth();
        $end = $start->addMonths($months);
        $monthKeys = [];
        for ($m = $start; $m->lessThan($end); $m = $m->addMonth()) {
            $monthKeys[] = $m->format('Y-m');
        }

        $rules = SurchargeRule::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $shifts = ScheduledShift::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('date', [$start->toDateString(), $end->subDay()->toDateString()])
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->with('shiftType')
            ->get();

        if ($rules->isEmpty() || $shifts->isEmpty()) {
            return ['months' => $monthKeys, 'rows' => [], 'totals' => array_fill_keys($monthKeys, 0)];
        }

        $teamsByUser = $this->teamsByUser(array_values(array_map('intval', $shifts->pluck('user_id')->unique()->all())));
        $tz = Tz::current();

        /** @var array<string, array{label: string, minutes: array<string, int>}> $acc */
        $acc = [];
        $totals = array_fill_keys($monthKeys, 0);

        foreach ($shifts as $shift) {
            $startTime = $shift->resolvedStartTime();
            $endTime = $shift->resolvedEndTime();
            if ($startTime === null || $endTime === null) {
                continue;
            }

            // Dienstzeiten sind lokale Wanduhrzeiten — direkt lokal aufbauen
            // (gleiche Basis wie die Export-Zerlegung nach Tz-Konvertierung).
            $date = $shift->date->toDateString();
            $shiftStart = CarbonImmutable::parse($date . ' ' . $startTime, $tz);
            $shiftEnd = CarbonImmutable::parse($date . ' ' . $endTime, $tz);
            if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                $shiftEnd = $shiftEnd->addDay();
            }

            $context = [
                'team_ids' => $teamsByUser[(int) $shift->user_id] ?? [],
                'site_id' => null,
                'shift_type_id' => $shift->shift_type_id !== null ? (int) $shift->shift_type_id : null,
            ];
            $applicable = $rules->filter(fn (SurchargeRule $rule): bool => $rule->matchesContext($context));
            if ($applicable->isEmpty()) {
                continue;
            }

            foreach ($this->calculator->calculate($shiftStart, $shiftEnd, $applicable) as $share) {
                $month = substr($share->date, 0, 7);
                if (! in_array($month, $monthKeys, true)) {
                    continue; // Überhang eines Nachtdienstes in den Folgemonat außerhalb des Fensters
                }
                $code = $share->rule->wage_type_code ?? $share->rule->wageType();
                if (! isset($acc[$code])) {
                    $acc[$code] = ['label' => (string) $share->rule->label, 'minutes' => array_fill_keys($monthKeys, 0)];
                }
                $acc[$code]['minutes'][$month] += $share->minutes;
                $totals[$month] += $share->minutes;
            }
        }

        ksort($acc);
        $rows = [];
        foreach ($acc as $code => $row) {
            $rows[] = [
                'wage_type_code' => (string) $code,
                'label' => $row['label'],
                'minutes' => $row['minutes'],
                'total' => array_sum($row['minutes']),
            ];
        }

        return ['months' => $monthKeys, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Teams aller betroffenen User in einem Rutsch (team_user-Pivot).
     *
     * @param  list<int>  $userIds
     * @return array<int, list<int>>
     */
    private function teamsByUser(array $userIds): array {
        if ($userIds === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('team_user')->whereIn('user_id', $userIds)->get(['user_id', 'team_id']) as $row) {
            $out[(int) $row->user_id][] = (int) $row->team_id;
        }

        return $out;
    }
}
