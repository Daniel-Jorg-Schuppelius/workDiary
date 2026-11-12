<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SicknessReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesReportScope, ResolvesStandardReportFilters};
use App\Models\Absence\SickLeave;
use App\Models\Platform\User;
use App\Services\Reporting\SicknessReportBuilder;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Krankheits-Report:
 * - Krankheitstage je Mitarbeiter im gewählten Zeitraum (Werktage + Kalendertage)
 * - Anzahl Krankheitsfälle / Folgebescheinigungen
 * - aktueller Lohnfortzahlungs-Status (§ 3 EntgFG)
 */
class SicknessReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;

    public function __construct(private readonly SicknessReportBuilder $builder) {}

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $seesTeam] = $this->resolveScopeWithPermission($request, Permission::SickLeaveViewAny);

        [$fromDate, $toDate] = $this->resolveRange($request);

        $filters = $this->standardFilters($request, ['user', 'team'], $fromDate, $toDate, scope: $scope);

        $leaves = $this->builder->leaves($fromDate, $toDate, $scope === 'mine' ? $userId : null, $filters);
        $rows = $this->builder->rows($fromDate, $toDate, $leaves);
        $totals = $this->builder->totals($rows);
        $months = $this->buildBucketsInRange($fromDate, $toDate);
        $monthlySeries = $this->monthlySickSeries($fromDate, $toDate, $leaves, $months);

        return view('reports.sickness', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'scope' => $scope,
            'seesTeam' => $seesTeam,
            'rows' => $rows,
            'totals' => $totals,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'monthlySeries' => $monthlySeries,
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            'monthlyMedian' => $monthlySeries === [] ? null : NumberHelper::median(array_column($monthlySeries, 'y')),
            'monthLabels' => array_column($months, 'shortLabel'),
            'heatmapRows' => $this->userMonthHeatmapRows($fromDate, $toDate, $leaves, $rows, $months),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Kranktage (Werktage) je Bucket (adaptiv zur Header-Granularität) über
     * alle gefilterten Mitarbeiter.
     *
     * @param  Collection<int, SickLeave>  $leaves
     * @param  list<array{key:string,label:string,shortLabel:string}>  $months
     * @return list<array{x: string, y: int}>
     */
    private function monthlySickSeries(CarbonImmutable $from, CarbonImmutable $to, Collection $leaves, array $months): array {
        if ($leaves->isEmpty() || $months === []) {
            return []; // Leerzustand statt Null-Achse (§Diagramm-UX).
        }

        /** @var array<string, int> $buckets */
        $buckets = [];
        foreach ($months as $month) {
            $buckets[$month['key']] = 0;
        }
        foreach ($leaves as $leave) {
            $this->addWorkdaysPerBucket($buckets, $leave, $from, $to);
        }
        if (array_sum($buckets) === 0) {
            return [];
        }

        $series = [];
        foreach ($months as $month) {
            $series[] = ['x' => $month['shortLabel'], 'y' => $buckets[$month['key']]];
        }

        return $series;
    }

    /**
     * Heatmap Mitarbeiter × Monat (Kranktage als Werktage).
     *
     * @param  Collection<int, SickLeave>  $leaves
     * @param  array<int, array{user: User, sick_workdays:int, sick_calendar_days:int, episodes:int, follow_ups:int, with_au:int, entitlement_days:int, used_days:int, remaining_days:int, exhausted:bool, chain_start:?string, exhaustion_date:?string}>  $rows
     * @param  list<array{key:string,label:string,shortLabel:string}>  $months
     * @return list<array{label: string, cells: list<array{value: int}>}>
     */
    private function userMonthHeatmapRows(CarbonImmutable $from, CarbonImmutable $to, Collection $leaves, array $rows, array $months): array {
        if ($leaves->isEmpty() || $months === [] || $rows === []) {
            return []; // Leerzustand statt Null-Matrix (§Diagramm-UX).
        }

        /** @var array<int, array<string, int>> $byUserMonth */
        $byUserMonth = [];
        $emptyBuckets = [];
        foreach ($months as $month) {
            $emptyBuckets[$month['key']] = 0;
        }
        foreach ($leaves as $leave) {
            $uid = (int) $leave->user_id;
            $byUserMonth[$uid] ??= $emptyBuckets;
            $this->addWorkdaysPerBucket($byUserMonth[$uid], $leave, $from, $to);
        }

        $heatmap = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user']->id;
            $cells = [];
            foreach ($months as $month) {
                $cells[] = ['value' => $byUserMonth[$uid][$month['key']] ?? 0];
            }
            $heatmap[] = ['label' => (string) $r['user']->name, 'cells' => $cells];
        }

        return $heatmap;
    }

    /**
     * Verteilt die Werktage einer Krankmeldung (auf den Report-Zeitraum
     * geklammert) tageweise auf ihre Abschnitte — Tag, Woche oder Monat.
     *
     * @param  array<string, int>  $buckets
     */
    private function addWorkdaysPerBucket(array &$buckets, SickLeave $leave, CarbonImmutable $from, CarbonImmutable $to): void {
        $granularity = $this->bucketGranularity($from, $to);
        $start = $leave->start_date->greaterThan($from) ? CarbonImmutable::parse($leave->start_date->toDateString()) : $from;
        $end = $leave->end_date->lessThan($to) ? CarbonImmutable::parse($leave->end_date->toDateString()) : $to;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = ChartBucket::keyLabel($granularity, $day)[0];
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += $this->builder->workdays($day, $day);
            }
        }
    }
}
