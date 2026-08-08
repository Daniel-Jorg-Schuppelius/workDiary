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

use App\Enums\Sickness\SickLeaveKind;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesReportScope, ResolvesStandardReportFilters};
use App\Models\{SickLeave, User};
use App\Services\HolidayService;
use App\Services\Reporting\ReportFilters;
use App\Services\Sickness\ContinuedPaymentService;
use App\Support\ChartBucket;
use Carbon\{CarbonImmutable, CarbonInterface};
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

    public function __construct(
        private readonly HolidayService $holidayService,
        private readonly ContinuedPaymentService $continuedPayment,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->resolveRange($request);

        $filters = $this->standardFilters($request, ['user', 'team'], $fromDate, $toDate, scope: $scope);

        $leaves = $this->loadLeaves($fromDate, $toDate, $scope, $userId, $filters);
        $rows = $this->aggregate($fromDate, $toDate, $leaves);
        $totals = $this->totals($rows);
        $months = $this->buildBucketsInRange($fromDate, $toDate);
        $monthlySeries = $this->monthlySickSeries($fromDate, $toDate, $leaves, $months);

        return view('reports.sickness', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
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
     * Krankmeldungen des Zeitraums (Scope + Standardfilter angewandt) —
     * eine Query für Tabelle und beide Diagramme.
     *
     * @return Collection<int, SickLeave>
     */
    private function loadLeaves(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): Collection {
        $q = SickLeave::query()
            ->whereNull('cancelled_at')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString());
        if ($scope === 'mine') {
            $q->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($q);

        /** @var Collection<int, SickLeave> $leaves */
        $leaves = $q->with('attachments')->get();

        return $leaves;
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
            $this->addWorkdaysPerMonth($buckets, $leave, $from, $to);
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
            $this->addWorkdaysPerMonth($byUserMonth[$uid], $leave, $from, $to);
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
     * geklammert) monatsweise auf die übergebenen Buckets.
     *
     * @param  array<string, int>  $buckets
     */
    private function addWorkdaysPerMonth(array &$buckets, SickLeave $leave, CarbonImmutable $from, CarbonImmutable $to): void {
        $granularity = $this->bucketGranularity($from, $to);
        $start = $leave->start_date->greaterThan($from) ? CarbonImmutable::parse($leave->start_date->toDateString()) : $from;
        $end = $leave->end_date->lessThan($to) ? CarbonImmutable::parse($leave->end_date->toDateString()) : $to;
        if ($start->greaterThan($end)) {
            return;
        }

        $cursor = $start->startOfMonth();
        while ($cursor->lte($end)) {
            $monthKey = ChartBucket::keyLabel($granularity, $cursor)[0];
            $chunkStart = $start->greaterThan($cursor) ? $start : $cursor;
            $chunkEnd = $end->lessThan($cursor->endOfMonth()) ? $end : $cursor->endOfMonth();
            if (array_key_exists($monthKey, $buckets)) {
                $buckets[$monthKey] += $this->countWorkdays($chunkStart, $chunkEnd);
            }
            $cursor = $cursor->addMonth();
        }
    }
    /**
     * @param  Collection<int, SickLeave>  $leaves
     * @return array<int, array{
     *   user: User,
     *   sick_workdays:int,
     *   sick_calendar_days:int,
     *   episodes:int,
     *   follow_ups:int,
     *   with_au:int,
     *   entitlement_days:int,
     *   used_days:int,
     *   remaining_days:int,
     *   exhausted:bool,
     *   chain_start:?string,
     *   exhaustion_date:?string
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, Collection $leaves): array {
        /** @var array<int, array{workdays:int, cal:int, episodes:int, follow:int, with_au:int}> $byUser */
        $byUser = [];
        foreach ($leaves as $s) {
            $uid = (int) $s->user_id;
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = ['workdays' => 0, 'cal' => 0, 'episodes' => 0, 'follow' => 0, 'with_au' => 0];
            }
            $start = $s->start_date->greaterThan($from) ? $s->start_date->copy() : $from->copy();
            $end = $s->end_date->lessThan($to) ? $s->end_date->copy() : $to->copy();
            if ($start->greaterThan($end)) {
                continue;
            }
            $byUser[$uid]['workdays'] += $this->countWorkdays($start, $end);
            $byUser[$uid]['cal'] += (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
            $byUser[$uid]['episodes']++;
            if ($s->kind === SickLeaveKind::FollowUp) {
                $byUser[$uid]['follow']++;
            }
            if ($s->attachments->isNotEmpty()) {
                $byUser[$uid]['with_au']++;
            }
        }

        if ($byUser === []) {
            return [];
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', array_keys($byUser))->orderBy('name')->get();

        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            $data = $byUser[$uid];
            $status = $this->continuedPayment->statusFor($user);
            $rows[] = [
                'user' => $user,
                'sick_workdays' => $data['workdays'],
                'sick_calendar_days' => $data['cal'],
                'episodes' => $data['episodes'],
                'follow_ups' => $data['follow'],
                'with_au' => $data['with_au'],
                'entitlement_days' => $status->entitlementDays,
                'used_days' => $status->usedDays,
                'remaining_days' => max(0, $status->remainingDays),
                'exhausted' => $status->exhausted,
                'chain_start' => $status->chainStart?->toDateString(),
                'exhaustion_date' => $status->exhaustionDate?->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{user:User, sick_workdays:int, sick_calendar_days:int, episodes:int, follow_ups:int, with_au:int, entitlement_days:int, used_days:int, remaining_days:int, exhausted:bool, chain_start:?string, exhaustion_date:?string}>  $rows
     * @return array{users:int, sick_workdays:int, sick_calendar_days:int, episodes:int, follow_ups:int, with_au:int, exhausted:int}
     */
    private function totals(array $rows): array {
        $t = [
            'users' => count($rows),
            'sick_workdays' => 0,
            'sick_calendar_days' => 0,
            'episodes' => 0,
            'follow_ups' => 0,
            'with_au' => 0,
            'exhausted' => 0,
        ];
        foreach ($rows as $r) {
            $t['sick_workdays'] += $r['sick_workdays'];
            $t['sick_calendar_days'] += $r['sick_calendar_days'];
            $t['episodes'] += $r['episodes'];
            $t['follow_ups'] += $r['follow_ups'];
            $t['with_au'] += $r['with_au'];
            if ($r['exhausted']) {
                $t['exhausted']++;
            }
        }

        return $t;
    }

    private function countWorkdays(CarbonInterface $start, CarbonInterface $end): int {
        if ($start->greaterThan($end)) {
            return 0;
        }
        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            if ($cursor->isWeekday() && ! $this->holidayService->isHoliday($cursor)) {
                $count++;
            }
            // Zuweisung statt In-Place-Mutation: funktioniert für Carbon UND CarbonImmutable.
            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
