<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, WritesReportCsv};
use App\Models\{EmergencyAssignment, OnCallShift, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Notdienst-Auswertung: Bereitschaftsstunden und tatsächliche
 * Einsatzzeiten je Mitarbeiter im gewählten Zeitraum.
 */
class OnCallReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->globalDateRangeBounds();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId);
        $totals = $this->totals($rows);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $totals, $from, $to, $scope, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $totals, $from, $to, $scope, $request);
        }

        return view('reports.on-call', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }
    /**
     * @return array<int, array{
     *   user: User,
     *   shift_count: int,
     *   shift_minutes: int,
     *   assignment_count: int,
     *   assignment_minutes: int,
     *   ratio: float|null
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId): array {
        $shiftsQ = OnCallShift::query()
            ->where('is_archived', false)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from);
        $assignmentsQ = EmergencyAssignment::query()
            ->where('is_archived', false)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from);
        if ($scope === 'mine') {
            $shiftsQ->where('user_id', $userId);
            $assignmentsQ->where('user_id', $userId);
        }

        /** @var array<int, array{shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int}> $byUser */
        $byUser = [];
        $ensure = static function (array &$byUser, int $uid): void {
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'shift_count' => 0,
                    'shift_minutes' => 0,
                    'assignment_count' => 0,
                    'assignment_minutes' => 0,
                ];
            }
        };

        foreach ($shiftsQ->get() as $shift) {
            $uid = (int) $shift->user_id;
            $ensure($byUser, $uid);
            $start = $shift->start_at->greaterThan($from) ? $shift->start_at : $from;
            $end = $shift->end_at->lessThan($to) ? $shift->end_at : $to;
            $minutes = max(0, (int) $start->diffInMinutes($end, true));
            $byUser[$uid]['shift_count']++;
            $byUser[$uid]['shift_minutes'] += $minutes;
        }

        foreach ($assignmentsQ->get() as $assignment) {
            $uid = (int) $assignment->user_id;
            $ensure($byUser, $uid);
            $start = $assignment->start_at->greaterThan($from) ? $assignment->start_at : $from;
            $end = $assignment->end_at->lessThan($to) ? $assignment->end_at : $to;
            $minutes = max(0, (int) $start->diffInMinutes($end, true));
            $byUser[$uid]['assignment_count']++;
            $byUser[$uid]['assignment_minutes'] += $minutes;
        }

        if ($byUser === []) {
            return [];
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', array_keys($byUser))->orderBy('name')->get();
        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            $u = $byUser[$uid];
            $ratio = $u['shift_minutes'] > 0
                ? $u['assignment_minutes'] / $u['shift_minutes']
                : null;
            $rows[] = [
                'user' => $user,
                'shift_count' => (int) $u['shift_count'],
                'shift_minutes' => (int) $u['shift_minutes'],
                'assignment_count' => (int) $u['assignment_count'],
                'assignment_minutes' => (int) $u['assignment_minutes'],
                'ratio' => $ratio,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @return array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}
     */
    private function totals(array $rows): array {
        $shiftCount = 0;
        $shiftMin = 0;
        $assignCount = 0;
        $assignMin = 0;
        foreach ($rows as $r) {
            $shiftCount += $r['shift_count'];
            $shiftMin += $r['shift_minutes'];
            $assignCount += $r['assignment_count'];
            $assignMin += $r['assignment_minutes'];
        }

        return [
            'users' => count($rows),
            'shift_count' => $shiftCount,
            'shift_minutes' => $shiftMin,
            'assignment_count' => $assignCount,
            'assignment_minutes' => $assignMin,
            'ratio' => $shiftMin > 0 ? $assignMin / $shiftMin : null,
        ];
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @param  array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}  $totals
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to, string $scope, Request $request): Response {
        $filename = sprintf('notdienst_%s_%s.csv', $from, $to);
        $fmt = static function (int $minutes): string {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return sprintf('%d:%02d', $h, $m);
        };

        $out = [['Mitarbeiter', 'Schichten', 'Bereitschaft (h)', 'Einsätze', 'Einsatzzeit (h)', 'Aktiv-Anteil %']];
        foreach ($rows as $r) {
            $out[] = [
                (string) $r['user']->name,
                $r['shift_count'],
                $fmt($r['shift_minutes']),
                $r['assignment_count'],
                $fmt($r['assignment_minutes']),
                $r['ratio'] !== null ? NumberHelper::toUSFormat($r['ratio'] * 100, 1) : '',
            ];
        }
        $out[] = [
            'Gesamt',
            $totals['shift_count'],
            $fmt($totals['shift_minutes']),
            $totals['assignment_count'],
            $fmt($totals['assignment_minutes']),
            $totals['ratio'] !== null ? NumberHelper::toUSFormat($totals['ratio'] * 100, 1) : '',
        ];

        return $this->csvWithMetadata($out, $filename, 'on-call', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $request);
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @param  array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}  $totals
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, string $scope, Request $request): SymfonyResponse {
        $filename = sprintf('notdienst_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.on-call', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $filename, request: $request, reportCode: 'on-call', filters: ['from' => $from, 'to' => $to, 'scope' => $scope]);
    }
}
