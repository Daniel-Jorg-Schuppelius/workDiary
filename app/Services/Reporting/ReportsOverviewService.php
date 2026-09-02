<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportsOverviewService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\{Project, TimeEntry, User};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kennzahlen + Übersichts-Charts der Auswertungs-Landing (reports.index).
 * Bewusst rein persönlich (eigene Zeiteinträge im globalen Zeitraum) —
 * org-weite Zahlen leben in den jeweils permission-gegateten Einzelreports.
 * DashboardService passt nicht: der ist „jetzt"-verankert (Onboarding/heute),
 * nicht zeitraumbasiert.
 */
final class ReportsOverviewService {
    private const TOP_PROJECTS = 10;

    /**
     * @return array{
     *     totalMinutes: int,
     *     bookedDays: int,
     *     activeProjects: int,
     *     avgMinutesPerDay: int,
     *     hoursSeries: list<array{x: string, y: float}>,
     *     hoursSeriesLabel: string,
     *     topProjects: list<array{x: string, y: float}>,
     * }
     */
    public function build(User $user, CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', DateRange::days($from, $to))
            ->get(['date', 'minutes', 'project_id']);

        $totalMinutes = (int) $entries->sum('minutes');
        $bookedDays = $entries->pluck('date')->map(fn($d): string => CarbonImmutable::parse((string) $d)->toDateString())->unique()->count();
        $activeProjects = $entries->pluck('project_id')->unique()->count();

        [$series, $seriesLabel] = $this->bucketedHours($entries, $from, $to);

        return [
            'totalMinutes' => $totalMinutes,
            'bookedDays' => $bookedDays,
            'activeProjects' => $activeProjects,
            'avgMinutesPerDay' => $bookedDays > 0 ? (int) round($totalMinutes / $bookedDays) : 0,
            'hoursSeries' => $series,
            'hoursSeriesLabel' => $seriesLabel,
            'topProjects' => $this->topProjects($entries),
        ];
    }

    /**
     * Adaptive Buckets: Tage (≤ 31 Tage), ISO-Wochen (≤ ~6 Monate), sonst Monate.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return array{0: list<array{x: string, y: float}>, 1: string}
     */
    private function bucketedHours(Collection $entries, CarbonImmutable $from, CarbonImmutable $to): array {
        // startOfDay-normalisiert: diffInDays ist in Carbon 3 ein Float und
        // wäre mit endOfDay-Grenze 30,99… statt 31.
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        [$keyFn, $labelFn, $step, $label] = match (true) {
            $days <= 31 => [
                fn(CarbonImmutable $d): string => $d->toDateString(),
                fn(CarbonImmutable $d): string => $d->isoFormat('DD.MM.'),
                fn(CarbonImmutable $d): CarbonImmutable => $d->addDay(),
                __('Stunden pro Tag'),
            ],
            $days <= 190 => [
                fn(CarbonImmutable $d): string => $d->isoFormat('GGGG-WW'),
                fn(CarbonImmutable $d): string => __('KW') . ' ' . $d->isoWeek,
                fn(CarbonImmutable $d): CarbonImmutable => $d->addWeek(),
                __('Stunden pro Woche'),
            ],
            default => [
                fn(CarbonImmutable $d): string => $d->format('Y-m'),
                fn(CarbonImmutable $d): string => $d->isoFormat('MMM YY'),
                fn(CarbonImmutable $d): CarbonImmutable => $d->addMonth(),
                __('Stunden pro Monat'),
            ],
        };

        $minutesByKey = [];
        foreach ($entries as $entry) {
            $key = $keyFn(CarbonImmutable::parse((string) $entry->date));
            $minutesByKey[$key] = ($minutesByKey[$key] ?? 0) + (int) $entry->minutes;
        }

        $series = [];
        $cursor = $from;
        $guard = 0;
        while ($cursor->lte($to) && $guard < 400) {
            $key = $keyFn($cursor);
            $series[] = ['x' => $labelFn($cursor), 'y' => round(($minutesByKey[$key] ?? 0) / 60, 1)];
            $cursor = $step($cursor);
            $guard++;
        }

        return [$series, $label];
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<array{x: string, y: float}>
     */
    private function topProjects(Collection $entries): array {
        $byProject = $entries->groupBy('project_id')
            ->map(fn(Collection $group): int => (int) $group->sum('minutes'))
            ->sortDesc()
            ->take(self::TOP_PROJECTS);

        if ($byProject->isEmpty()) {
            return [];
        }

        $names = Project::query()->with('customer')
            ->whereIn('id', $byProject->keys())
            ->get()->keyBy('id');

        $series = $byProject->map(function (int $minutes, int $projectId) use ($names): array {
            $project = $names->get($projectId);
            $label = $project !== null ? $project->name : ('#' . $projectId);
            if ($project?->customer !== null) {
                $label .= ' · ' . $project->customer->name;
            }

            return ['x' => $label, 'y' => round($minutes / 60, 1)];
        })->values();

        return array_values($series->all());
    }
}
