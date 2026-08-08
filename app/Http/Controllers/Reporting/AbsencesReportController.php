<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsencesReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{FlexBalance, SickLeave, User, Vacation};
use App\Services\Absence\VacationBalanceService;
use App\Services\HolidayService;
use App\Services\Reporting\ReportFilters;
use App\Support\ChartBucket;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Urlaubs- und Flex-Auswertung: Werktage pro Abwesenheits-Typ
 * sowie Flex-Bewegung und aktueller Flex-Saldo je Mitarbeiter.
 */
class AbsencesReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    /** Top-N-Kappung des Resturlaub-Balkendiagramms. */
    private const REMAINING_TOP_N = 15;

    public function __construct(
        private readonly HolidayService $holidayService,
        private readonly VacationBalanceService $balanceService,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $filters = $this->standardFilters(
            $request,
            ['user', 'team', 'status'],
            $fromDate,
            $toDate,
            [VacationStatus::Pending->value, VacationStatus::Approved->value],
            scope: $scope,
        );

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId, $filters);
        $totals = $this->totals($rows);

        // MVP-413: Urlaubskonto-Spalten (Anspruch+Übertrag/Rest) für das Jahr des Bereichsendes.
        $balanceYear = (int) $toDate->year;
        foreach ($rows as $i => $r) {
            $balance = $this->balanceService->balanceFor((int) $r['user']->id, $balanceYear);
            $rows[$i]['entitled_total_days'] = $balance->hasEntitlement ? $balance->totalDays() : null;
            $rows[$i]['remaining_days'] = $balance->hasEntitlement ? $balance->remainingDays() : null;
        }

        $exportFilters = array_merge(['scope' => $scope], $filters->toAuditArray());
        $monthlyTypeSeries = $this->monthlyTypeSeries($fromDate, $toDate, $scope, $userId, $filters);
        $typeBands = $this->typeBands();

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $totals, $from, $to, $balanceYear, $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $totals, $from, $to, $scope, $balanceYear, $monthlyTypeSeries, $typeBands, $exportFilters, $request);
        }

        return view('reports.absences', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
            'balanceYear' => $balanceYear,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team', 'status'],
            'monthlyTypeSeries' => $monthlyTypeSeries,
            'typeBands' => $typeBands,
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            'remainingSeries' => $this->remainingVacationSeries($rows),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Bänder des Monats-Stapeldiagramms (vorhandene totals-Kategorien).
     *
     * @return list<array{key: string, label: string}>
     */
    private function typeBands(): array {
        return [
            ['key' => 'vacation', 'label' => __('Urlaub')],
            ['key' => 'sick', 'label' => __('Krank')],
            ['key' => 'special', 'label' => __('Sonder')],
            ['key' => 'unpaid', 'label' => __('Unbezahlt')],
        ];
    }

    /**
     * Abwesenheits-Werktage je Monat nach Typ (Chart-Datenkontrakt Screen + PDF).
     * Urlaubstypen zählen wie in der Tabelle nur genehmigte Anträge — außer der
     * Standardfilter „Status" wählt explizit einen anderen Status; Kranktage
     * kommen statusunabhängig aus den Krankmeldungen.
     *
     * @return list<array<string, string|int>>
     */
    private function monthlyTypeSeries(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $granularity = $this->bucketGranularity($from, $to);
        $bucketList = $this->buildBucketsInRange($from, $to);
        if ($bucketList === []) {
            return [];
        }

        /** @var array<string, array<string, int>> $buckets [bucketKey][band] => Werktage */
        $buckets = [];
        foreach ($bucketList as $bucket) {
            $buckets[$bucket['key']] = ['vacation' => 0, 'sick' => 0, 'special' => 0, 'unpaid' => 0];
        }

        $vacQ = Vacation::query();
        if ($scope === 'mine') {
            $vacQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($vacQ);
        $wantedStatus = $filters->status ?? VacationStatus::Approved->value;
        $vacQ->where('status', $wantedStatus);
        $vacQ->scopes(['overlapping' => [$from, $to]]);
        /** @var Collection<int, Vacation> $vacations */
        $vacations = $vacQ->get();
        foreach ($vacations as $v) {
            $bandKey = match ($v->type) {
                VacationType::Vacation => 'vacation',
                VacationType::Special => 'special',
                VacationType::Unpaid => 'unpaid',
                default => null,
            };
            if ($bandKey === null) {
                continue;
            }
            $this->addWorkdaysPerBucket($buckets, $granularity, $bandKey, $v->start_date, $v->end_date, $from, $to);
        }

        $sickQ = SickLeave::query()
            ->whereNull('cancelled_at')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString());
        if ($scope === 'mine') {
            $sickQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($sickQ);
        /** @var Collection<int, SickLeave> $sickLeaves */
        $sickLeaves = $sickQ->get();
        foreach ($sickLeaves as $s) {
            $this->addWorkdaysPerBucket($buckets, $granularity, 'sick', $s->start_date, $s->end_date, $from, $to);
        }

        $total = 0;
        foreach ($buckets as $bucket) {
            $total += array_sum($bucket);
        }
        if ($total === 0) {
            return []; // Leerzustand statt Null-Achse (§Diagramm-UX).
        }

        $series = [];
        foreach ($bucketList as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], ...$buckets[$bucket['key']]];
        }

        return $series;
    }

    /**
     * Verteilt die Werktage einer Abwesenheit (auf den Report-Zeitraum
     * geklammert) tagesweise auf die adaptiven Chart-Buckets.
     *
     * @param  array<string, array<string, int>>  $buckets  [bucketKey][band] => Werktage
     * @param  'day'|'week'|'month'|'quarter'  $granularity
     */
    private function addWorkdaysPerBucket(array &$buckets, string $granularity, string $bandKey, CarbonInterface $startDate, CarbonInterface $endDate, CarbonImmutable $from, CarbonImmutable $to): void {
        $start = $startDate->greaterThan($from) ? CarbonImmutable::parse($startDate->toDateString()) : $from;
        $end = $endDate->lessThan($to) ? CarbonImmutable::parse($endDate->toDateString()) : $to;
        if ($start->greaterThan($end)) {
            return;
        }

        for ($cursor = $start->startOfDay(); $cursor->lte($end); $cursor = $cursor->addDay()) {
            if (! $cursor->isWeekday() || $this->holidayService->isHoliday($cursor)) {
                continue;
            }
            $key = ChartBucket::keyLabel($granularity, $cursor)[0];
            if (isset($buckets[$key])) {
                $buckets[$key][$bandKey] += 1;
            }
        }
    }

    /**
     * Resturlaub Top-N je Mitarbeiter (absteigend, aus den MVP-413-Spalten).
     *
     * @param  array<int, array{user: User, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int|null, entitled_total_days?:float|null, remaining_days?:float|null}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function remainingVacationSeries(array $rows): array {
        $series = [];
        foreach ($rows as $r) {
            $remaining = $r['remaining_days'] ?? null;
            if ($remaining === null) {
                continue;
            }
            $series[] = ['x' => (string) $r['user']->name, 'y' => round((float) $remaining, 1)];
        }
        usort($series, static fn(array $a, array $b): int => $b['y'] <=> $a['y']);

        return array_slice($series, 0, self::REMAINING_TOP_N);
    }
    /**
     * @return array<int, array{
     *   user: User,
     *   vacation_days:int,
     *   sick_days:int,
     *   special_days:int,
     *   unpaid_days:int,
     *   pending_days:int,
     *   flex_change_minutes:int,
     *   flex_balance_minutes:int|null
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $vacQ = Vacation::query();
        if ($scope === 'mine') {
            $vacQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($vacQ);
        if ($filters->status !== null) {
            $vacQ->where('status', $filters->status);
        }
        $vacQ->scopes(['overlapping' => [$from, $to]]);
        /** @var Collection<int, Vacation> $vacations */
        $vacations = $vacQ->get();

        $endYear = (int) $to->format('Y');
        $endMonth = (int) $to->format('n');
        $startYear = (int) $from->format('Y');
        $startMonth = (int) $from->format('n');

        $flexQ = FlexBalance::query();
        if ($scope === 'mine') {
            $flexQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($flexQ);
        /** @var Collection<int, FlexBalance> $flexAll */
        $flexAll = $flexQ->get();

        /** @var array<int, array{vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int}> $absByUser */
        $absByUser = [];
        $ensure = static function (array &$arr, int $uid): void {
            if (! isset($arr[$uid])) {
                $arr[$uid] = [
                    'vacation_days' => 0,
                    'sick_days' => 0,
                    'special_days' => 0,
                    'unpaid_days' => 0,
                    'pending_days' => 0,
                ];
            }
        };

        foreach ($vacations as $v) {
            $uid = (int) $v->user_id;
            $ensure($absByUser, $uid);
            $start = $v->start_date->greaterThan($from) ? $v->start_date->copy() : $from->copy();
            $end = $v->end_date->lessThan($to) ? $v->end_date->copy() : $to->copy();
            $days = $this->countWorkdays($start, $end);
            if ($days <= 0) {
                continue;
            }
            if ($v->status === VacationStatus::Pending) {
                $absByUser[$uid]['pending_days'] += $days;

                continue;
            }
            if ($v->status !== VacationStatus::Approved) {
                continue;
            }
            match ($v->type) {
                VacationType::Vacation => $absByUser[$uid]['vacation_days'] += $days,
                VacationType::Special => $absByUser[$uid]['special_days'] += $days,
                VacationType::Unpaid => $absByUser[$uid]['unpaid_days'] += $days,
                default => null,
            };
        }

        $sickQ = SickLeave::query()
            ->whereNull('cancelled_at')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString());
        if ($scope === 'mine') {
            $sickQ->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($sickQ);
        /** @var Collection<int, SickLeave> $sickLeaves */
        $sickLeaves = $sickQ->get();
        foreach ($sickLeaves as $s) {
            $uid = (int) $s->user_id;
            $ensure($absByUser, $uid);
            $start = $s->start_date->greaterThan($from) ? $s->start_date->copy() : $from->copy();
            $end = $s->end_date->lessThan($to) ? $s->end_date->copy() : $to->copy();
            $days = $this->countWorkdays($start, $end);
            if ($days > 0) {
                $absByUser[$uid]['sick_days'] += $days;
            }
        }

        /** @var array<int, int> $flexChange */
        $flexChange = [];
        /** @var array<int, array{key:int, balance:int}> $flexBalanceLatest */
        $flexBalanceLatest = [];
        $fromKey = $startYear * 100 + $startMonth;
        $toKey = $endYear * 100 + $endMonth;
        foreach ($flexAll as $fb) {
            $key = (int) $fb->year * 100 + (int) $fb->month;
            $uid = (int) $fb->user_id;
            if ($key >= $fromKey && $key <= $toKey) {
                $flexChange[$uid] = ($flexChange[$uid] ?? 0) + ((int) $fb->actual_minutes - (int) $fb->target_minutes);
            }
            if ($key <= $toKey) {
                if (! isset($flexBalanceLatest[$uid]) || $flexBalanceLatest[$uid]['key'] < $key) {
                    $flexBalanceLatest[$uid] = ['key' => $key, 'balance' => (int) $fb->balance_minutes];
                }
            }
        }

        $userIds = array_unique(array_merge(array_keys($absByUser), array_keys($flexChange), array_keys($flexBalanceLatest)));
        if ($userIds === []) {
            return [];
        }
        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', $userIds)->orderBy('name')->get();

        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            $abs = $absByUser[$uid] ?? [
                'vacation_days' => 0,
                'sick_days' => 0,
                'special_days' => 0,
                'unpaid_days' => 0,
                'pending_days' => 0,
            ];
            $rows[] = [
                'user' => $user,
                'vacation_days' => $abs['vacation_days'],
                'sick_days' => $abs['sick_days'],
                'special_days' => $abs['special_days'],
                'unpaid_days' => $abs['unpaid_days'],
                'pending_days' => $abs['pending_days'],
                'flex_change_minutes' => $flexChange[$uid] ?? 0,
                'flex_balance_minutes' => $flexBalanceLatest[$uid]['balance'] ?? null,
            ];
        }

        return $rows;
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

    /**
     * @param  array<int, array{user: User, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int|null}>  $rows
     * @return array{users:int, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int}
     */
    private function totals(array $rows): array {
        $t = [
            'users' => count($rows),
            'vacation_days' => 0,
            'sick_days' => 0,
            'special_days' => 0,
            'unpaid_days' => 0,
            'pending_days' => 0,
            'flex_change_minutes' => 0,
            'flex_balance_minutes' => 0,
        ];
        foreach ($rows as $r) {
            $t['vacation_days'] += $r['vacation_days'];
            $t['sick_days'] += $r['sick_days'];
            $t['special_days'] += $r['special_days'];
            $t['unpaid_days'] += $r['unpaid_days'];
            $t['pending_days'] += $r['pending_days'];
            $t['flex_change_minutes'] += $r['flex_change_minutes'];
            $t['flex_balance_minutes'] += $r['flex_balance_minutes'] ?? 0;
        }

        return $t;
    }

    /**
     * @param  array<int, array{user: User, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int|null, entitled_total_days?:float|null, remaining_days?:float|null}>  $rows
     * @param  array{users:int, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int}  $totals
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to, int $balanceYear, array $exportFilters, Request $request): Response {
        $filename = sprintf('abwesenheiten_%s_%s.csv', $from, $to);
        $fmt = static function (int $m): string {
            $sign = $m < 0 ? '-' : '';
            $abs = abs($m);

            return sprintf('%s%d:%02d', $sign, intdiv($abs, 60), $abs % 60);
        };
        $fmtDays = static fn(?float $d): string => $d !== null ? number_format($d, 1, ',', '') : '';
        $out = [['Mitarbeiter', 'Urlaub (Werktage)', 'Krank', 'Sonderurlaub', 'Unbezahlt', 'Ausstehend', sprintf('Anspruch %d', $balanceYear), sprintf('Rest %d', $balanceYear), 'Flex-Änderung', 'Flex-Saldo']];
        foreach ($rows as $r) {
            $out[] = [
                (string) $r['user']->name,
                $r['vacation_days'],
                $r['sick_days'],
                $r['special_days'],
                $r['unpaid_days'],
                $r['pending_days'],
                $fmtDays($r['entitled_total_days'] ?? null),
                $fmtDays($r['remaining_days'] ?? null),
                $fmt($r['flex_change_minutes']),
                $r['flex_balance_minutes'] !== null ? $fmt($r['flex_balance_minutes']) : '',
            ];
        }
        $out[] = [
            'Gesamt',
            $totals['vacation_days'],
            $totals['sick_days'],
            $totals['special_days'],
            $totals['unpaid_days'],
            $totals['pending_days'],
            '',
            '',
            $fmt($totals['flex_change_minutes']),
            $fmt($totals['flex_balance_minutes']),
        ];

        return $this->csvWithMetadata($out, $filename, 'absences', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{user: User, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int|null, entitled_total_days?:float|null, remaining_days?:float|null}>  $rows
     * @param  array{users:int, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int}  $totals
     * @param  list<array<string, string|int>>  $monthlyTypeSeries
     * @param  list<array{key: string, label: string}>  $typeBands
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, string $scope, int $balanceYear, array $monthlyTypeSeries, array $typeBands, array $exportFilters, Request $request): SymfonyResponse {
        $filename = sprintf('abwesenheiten_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.absences', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'balanceYear' => $balanceYear,
            'chart' => [
                'type' => 'stacked-bar-h',
                'title' => __('Abwesenheitstage je Monat nach Typ'),
                'unit' => __('Tage'),
                'xLabel' => __('Monat'),
                'series' => $monthlyTypeSeries,
                'bands' => $typeBands,
                'note' => __('Vereinfachte Druckdarstellung.'),
            ],
        ], $filename, 'landscape', $request, 'absences', $exportFilters);
    }
}
