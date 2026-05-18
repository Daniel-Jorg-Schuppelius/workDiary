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
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Notdienst-Auswertung: Bereitschaftsstunden und tatsächliche
 * Einsatzzeiten je Mitarbeiter im gewählten Zeitraum.
 */
class OnCallReportController extends Controller
{
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse
    {
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

        return view('reports.on-call', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    private function resolveScope(Request $request, bool $isAdmin): string
    {
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        return $scope;
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
    private function aggregate(Carbon $from, Carbon $to, string $scope, int $userId): array
    {
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
    private function totals(array $rows): array
    {
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
    private function exportCsv(array $rows, array $totals, string $from, string $to): Response
    {
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
                $r['ratio'] !== null ? number_format($r['ratio'] * 100, 1, '.', '') : '',
            ];
        }
        $out[] = [
            'Gesamt',
            $totals['shift_count'],
            $fmt($totals['shift_minutes']),
            $totals['assignment_count'],
            $fmt($totals['assignment_minutes']),
            $totals['ratio'] !== null ? number_format($totals['ratio'] * 100, 1, '.', '') : '',
        ];

        $csv = '';
        foreach ($out as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"'.str_replace('"', '""', $s).'"';
                }

                return $s;
            }, $row))."\r\n";
        }

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<int, array{user: User, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}>  $rows
     * @param  array{users:int, shift_count:int, shift_minutes:int, assignment_count:int, assignment_minutes:int, ratio:float|null}  $totals
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, string $scope): SymfonyResponse
    {
        $filename = sprintf('notdienst_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.on-call', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
