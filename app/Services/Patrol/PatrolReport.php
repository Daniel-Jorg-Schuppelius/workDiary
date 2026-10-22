<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Patrol;

use App\Enums\Patrol\PatrolRunStatus;
use App\Models\Patrol\{PatrolCheckpoint, PatrolRun};
use Carbon\CarbonImmutable;

/**
 * Rundgangsbericht über einen Zeitraum (MVP-1292): je Lauf Soll-Punkte,
 * pünktliche und verspätete Scans, verpasste Punkte und Begründung; dazu die
 * Summen für den Auftraggeber.
 */
final class PatrolReport {
    /**
     * @return array{rows: list<array{run: PatrolRun, expected: int, on_time: int, late: int, missed: int}>, totals: array{runs: int, completed: int, aborted: int, expected: int, on_time: int, late: int, missed: int, punctuality: float|null}}
     */
    public function build(int $organizationId, CarbonImmutable $from, CarbonImmutable $to, ?int $routeId = null, ?int $siteId = null): array {
        $runs = PatrolRun::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('started_at', [$from, $to])
            ->when($routeId !== null, fn ($q) => $q->where('patrol_route_id', $routeId))
            ->when($siteId !== null, fn ($q) => $q->whereHas('route', fn ($r) => $r->where('site_id', $siteId)))
            ->with(['route:id,name,site_id', 'route.site:id,name', 'scans:id,patrol_run_id,in_window', 'starter:id,name'])
            ->orderBy('started_at')
            ->get();
        $expectedByRoute = PatrolCheckpoint::query()
            ->whereIn('patrol_route_id', $runs->pluck('patrol_route_id')->unique())
            ->selectRaw('patrol_route_id, COUNT(*) as aggregate')
            ->groupBy('patrol_route_id')
            ->pluck('aggregate', 'patrol_route_id');

        $rows = [];
        $totals = ['runs' => 0, 'completed' => 0, 'aborted' => 0, 'expected' => 0, 'on_time' => 0, 'late' => 0, 'missed' => 0, 'punctuality' => null];
        foreach ($runs as $run) {
            $expected = (int) ($expectedByRoute[$run->patrol_route_id] ?? 0);
            $onTime = $run->scans->where('in_window', true)->count();
            $late = $run->scans->count() - $onTime;
            $row = ['run' => $run, 'expected' => $expected, 'on_time' => $onTime, 'late' => $late, 'missed' => max(0, $expected - $run->scans->count())];
            $rows[] = $row;

            $totals['runs']++;
            $totals['completed'] += $run->status === PatrolRunStatus::Completed ? 1 : 0;
            $totals['aborted'] += $run->status === PatrolRunStatus::Aborted ? 1 : 0;
            foreach (['expected', 'on_time', 'late', 'missed'] as $key) {
                $totals[$key] += $row[$key];
            }
        }
        $totals['punctuality'] = $totals['expected'] > 0 ? round($totals['on_time'] / $totals['expected'] * 100, 1) : null;

        return ['rows' => $rows, 'totals' => $totals];
    }
}
