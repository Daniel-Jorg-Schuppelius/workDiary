<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Attendance\AttendanceStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{Attendance, TimeEntry, User, WorkSchedule};
use Carbon\{CarbonImmutable, CarbonInterface, CarbonPeriod};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Anwesenheits-Auswertung: tatsächliche Attendance-Minuten vs. WorkSchedule-Soll
 * und gebuchte TimeEntry-Minuten je Mitarbeiter.
 */
class AttendanceReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        $range = $this->globalDateRange();
        $from = $range['from'];
        $to = $range['to'];
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $rows = $this->aggregate($from, $to, $scope, $userId);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $fromStr, $toStr, $scope);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $fromStr, $toStr, $scope);
        }

        return view('reports.attendance', [
            'rows' => $rows,
            'from' => $fromStr,
            'to' => $toStr,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'totals' => $this->totals($rows),
        ]);
    }

    /**
     * @return array<int, array{
     *   user: User,
     *   attendance_minutes: int,
     *   time_entry_minutes: int,
     *   target_minutes: int,
     *   workdays: int,
     *   variance: int
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId): array {
        // Mandantengrenze: User hat KEINEN globalen OrganizationScope — ohne expliziten
        // Org-Filter erschienen im Team-Scope User aller Orgs als Zeilen (Tenant-Leak, Bauturbo A17).
        $usersQuery = User::query()
            ->where('organization_id', Auth::user()?->organization_id)
            ->orderBy('name');
        if ($scope === 'mine') {
            $usersQuery->where('id', $userId);
        }
        /** @var Collection<int, User> $users */
        $users = $usersQuery->get(['id', 'name']);

        if ($users->isEmpty()) {
            return [];
        }
        $userIds = $users->pluck('id')->map(static fn($v): int => (int) $v)->all();

        /** @var array<int, int> $attMinByUser */
        $attMinByUser = Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->selectRaw('user_id, COALESCE(SUM(duration_minutes), 0) as m')
            ->groupBy('user_id')
            ->pluck('m', 'user_id')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var array<int, int> $teMinByUser */
        $teMinByUser = TimeEntry::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('user_id, COALESCE(SUM(minutes), 0) as m')
            ->groupBy('user_id')
            ->pluck('m', 'user_id')
            ->map(static fn($v): int => (int) $v)
            ->all();

        /** @var Collection<int, WorkSchedule> $schedules */
        $schedules = WorkSchedule::query()
            ->whereIn('user_id', $userIds)
            ->where('valid_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $from->toDateString());
            })
            ->orderBy('user_id')
            ->orderBy('valid_from')
            ->get();

        /** @var array<int, array<int, WorkSchedule>> $schedByUser */
        $schedByUser = [];
        foreach ($schedules as $s) {
            $schedByUser[(int) $s->user_id][] = $s;
        }

        $rows = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $userSchedules = array_values($schedByUser[$uid] ?? []);
            [$workdays, $targetMin] = $this->computeTarget($from, $to, $userSchedules);
            $att = $attMinByUser[$uid] ?? 0;
            $te = $teMinByUser[$uid] ?? 0;
            $rows[] = [
                'user' => $u,
                'attendance_minutes' => $att,
                'time_entry_minutes' => $te,
                'target_minutes' => $targetMin,
                'workdays' => $workdays,
                'variance' => $att - $targetMin,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<WorkSchedule>  $schedules
     * @return array{0:int,1:int} [workdays, targetMinutes]
     */
    private function computeTarget(CarbonImmutable $from, CarbonImmutable $to, array $schedules): array {
        if ($schedules === []) {
            return [0, 0];
        }

        $workdays = 0;
        $target = 0;
        $period = CarbonPeriod::create($from->toDateString(), $to->toDateString());
        foreach ($period as $day) {
            $sched = $this->scheduleFor($day, $schedules);
            if ($sched === null) {
                continue;
            }
            $iso = (int) $day->dayOfWeekIso;
            $dayTarget = $sched->targetMinutesForWeekday($iso);
            if ($dayTarget <= 0 && ! $sched->appliesOnWeekday($iso)) {
                continue;
            }
            $workdays++;
            $target += $dayTarget;
        }

        return [$workdays, $target];
    }

    /**
     * @param  list<WorkSchedule>  $schedules
     */
    private function scheduleFor(CarbonInterface $day, array $schedules): ?WorkSchedule {
        $match = null;
        foreach ($schedules as $s) {
            if ($s->valid_from->lte($day) && ($s->valid_to === null || $s->valid_to->gte($day))) {
                if ($match === null || $s->valid_from->gt($match->valid_from)) {
                    $match = $s;
                }
            }
        }

        return $match;
    }

    /**
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     * @return array{attendance:int, time_entry:int, target:int, variance:int}
     */
    private function totals(array $rows): array {
        $att = 0;
        $te = 0;
        $tg = 0;
        foreach ($rows as $r) {
            $att += $r['attendance_minutes'];
            $te += $r['time_entry_minutes'];
            $tg += $r['target_minutes'];
        }

        return [
            'attendance' => $att,
            'time_entry' => $te,
            'target' => $tg,
            'variance' => $att - $tg,
        ];
    }

    /**
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     */
    private function exportCsv(array $rows, string $from, string $to, string $scope): Response {
        $filename = sprintf('anwesenheit_%s_%s.csv', $from, $to);
        $out = [];
        $out[] = ['Mitarbeiter', 'Arbeitstage', 'Soll (min)', 'Anwesend (min)', 'Gebucht (min)', 'Saldo (min)'];
        foreach ($rows as $r) {
            $out[] = [$r['user']->name, $r['workdays'], $r['target_minutes'], $r['attendance_minutes'], $r['time_entry_minutes'], $r['variance']];
        }
        $totals = $this->totals($rows);
        $out[] = ['GESAMT', '', $totals['target'], $totals['attendance'], $totals['time_entry'], $totals['variance']];

        return $this->csvWithMetadata($out, $filename, 'attendance', ['from' => $from, 'to' => $to, 'scope' => $scope]);
    }

    /**
     * @param  array<int, array{user: User, attendance_minutes:int, time_entry_minutes:int, target_minutes:int, workdays:int, variance:int}>  $rows
     */
    private function exportPdf(array $rows, string $from, string $to, string $scope): SymfonyResponse {
        $filename = sprintf('anwesenheit_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.attendance', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ], $filename);
    }
}
