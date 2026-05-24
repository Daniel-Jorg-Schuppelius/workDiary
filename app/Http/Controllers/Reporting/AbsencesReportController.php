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
use App\Models\{FlexBalance, SickLeave, User, Vacation};
use App\Services\HolidayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
    use ResolvesGlobalDateRange;

    public function __construct(private readonly HolidayService $holidayService) {}

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        $scope = $this->resolveScope($request, $isAdmin);

        $range = $this->globalDateRange();
        $fromDate = Carbon::parse($range['from']->toDateString())->startOfDay();
        $toDate = Carbon::parse($range['to']->toDateString())->endOfDay();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId);
        $totals = $this->totals($rows);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $totals, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $totals, $from, $to, $scope);
        }

        return view('reports.absences', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    private function resolveScope(Request $request, bool $isAdmin): string {
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        return $scope;
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
    private function aggregate(Carbon $from, Carbon $to, string $scope, int $userId): array {
        $vacQ = Vacation::query()->scopes(['overlapping' => [$from, $to]]);
        if ($scope === 'mine') {
            $vacQ->where('user_id', $userId);
        }
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

    private function countWorkdays(Carbon $start, Carbon $end): int {
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
            $cursor->addDay();
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
     * @param  array<int, array{user: User, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int|null}>  $rows
     * @param  array{users:int, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int}  $totals
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to): Response {
        $filename = sprintf('abwesenheiten_%s_%s.csv', $from, $to);
        $fmt = static function (int $m): string {
            $sign = $m < 0 ? '-' : '';
            $abs = abs($m);

            return sprintf('%s%d:%02d', $sign, intdiv($abs, 60), $abs % 60);
        };
        $out = [['Mitarbeiter', 'Urlaub (Werktage)', 'Krank', 'Sonderurlaub', 'Unbezahlt', 'Ausstehend', 'Flex-Änderung', 'Flex-Saldo']];
        foreach ($rows as $r) {
            $out[] = [
                (string) $r['user']->name,
                $r['vacation_days'],
                $r['sick_days'],
                $r['special_days'],
                $r['unpaid_days'],
                $r['pending_days'],
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
            $fmt($totals['flex_change_minutes']),
            $fmt($totals['flex_balance_minutes']),
        ];

        $csv = '';
        foreach ($out as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }

                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  array<int, array{user: User, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int|null}>  $rows
     * @param  array{users:int, vacation_days:int, sick_days:int, special_days:int, unpaid_days:int, pending_days:int, flex_change_minutes:int, flex_balance_minutes:int}  $totals
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, string $scope): SymfonyResponse {
        $filename = sprintf('abwesenheiten_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.absences', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
