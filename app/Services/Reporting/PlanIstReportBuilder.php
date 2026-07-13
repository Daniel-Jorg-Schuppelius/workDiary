<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Attendance, DiaryEntry, Project, ScheduledShift, Site, TimeEntry, User, WorkSchedule};
use App\Models\Location\LocationVisit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Plan/Ist-Report Builder (MVP-018, ../WorkDiary-Architecture/plan-ist-abgleich.md).
 *
 * Liefert Aggregate für drei Ebenen: Anwesenheit (§2.1), Projektzeit (§2.2)
 * und Schicht (§2.3), plus Standort-Aggregation (MVP-333 / Feature 007).
 *
 * Datenquellen der erweiterten Sichten (A14 · MVP-333):
 *  - Schicht: Soll = sichtbare geplante Schichten (published/confirmed, wie
 *    {@see \App\Services\CoverageService}) × Fensterdauer (resolvedStart/End,
 *    Übernacht-Schichten +1 Tag); Ist = Überlappung der Anwesenheits-
 *    intervalle der eingeteilten Person mit dem Schichtfenster (brutto —
 *    Soll-Fenster ist ebenfalls brutto). Schichten ohne Zeitfenster zählen
 *    Soll = 0 und als Ist die Tages-Anwesenheit (je Person+Tag nur einmal).
 *  - Projekt: Soll = Summe `DiaryEntry.planned_minutes` der im Zeitraum
 *    überlappenden Aufträge (Konzept §2.2); Projekte ohne geplante Aufträge
 *    werden als `noPlan` markiert (kein Alarm). Ist = TimeEntry-Minuten.
 *  - Standort: einzige direkte Standort↔Zeit-Verknüpfung im Datenmodell ist
 *    die standortbasierte Zeiterfassung (CustomerGeofence.site_id →
 *    LocationVisit). Solldaten je Standort existieren nicht (Schichten/
 *    Arbeitszeitmodelle sind nicht standortbezogen) — reine Ist-Verteilung,
 *    die Lücke wird in der UI explizit ausgewiesen.
 */
class PlanIstReportBuilder {
    /** Schwellen aus §2.1, später konfigurierbar. */
    private const LATE_START_THRESHOLD_MIN = 15;
    private const HOURS_DIFF_THRESHOLD_PERCENT = 10;

    /**
     * Persönlicher Anwesenheits-Plan/Ist pro Tag im Zeitraum.
     *
     * @return array<int, array{
     *     date: string,
     *     plan_minutes: int,
     *     actual_minutes: int,
     *     delta_minutes: int,
     *     plan_start: ?string,
     *     actual_start: ?string,
     *     late_start_minutes: ?int,
     *     warnings: list<string>,
     *     no_plan: bool,
     * }>
     */
    public function presenceFor(User $user, CarbonImmutable $from, CarbonImmutable $to): array {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $schedules = WorkSchedule::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $user->organization_id)
            ->where(function ($q) use ($to) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $to->toDateString());
            })
            ->where(function ($q) use ($from) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $from->toDateString());
            })
            ->orderBy('valid_from')
            ->get();

        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $user->organization_id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->groupBy(fn(Attendance $a) => $a->date?->format('Y-m-d') ?? '');

        $rows = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $schedule = $this->scheduleFor($schedules, $d);
            $dayAttendances = $attendances->get($key, new Collection());

            $planMinutes = 0;
            $planStart = null;
            $noPlan = true;
            if ($schedule && $schedule->appliesOnWeekday((int) $d->dayOfWeekIso)) {
                $planMinutes = $schedule->targetMinutesForWeekday((int) $d->dayOfWeekIso);
                $planStart = $schedule->core_start ? substr((string) $schedule->core_start, 0, 5) : null;
                $noPlan = false;
            }

            $actualMinutes = (int) $dayAttendances->sum('duration_minutes');
            /** @var Attendance|null $firstAtt */
            $firstAtt = $dayAttendances->sortBy('started_at')->first();
            $actualStart = $firstAtt?->started_at?->format('H:i');

            $delta = $actualMinutes - $planMinutes;
            $lateStart = null;
            if ($planStart && $actualStart) {
                $plan = CarbonImmutable::createFromFormat('H:i', $planStart);
                $actual = CarbonImmutable::createFromFormat('H:i', $actualStart);
                if ($plan && $actual) {
                    $lateStart = (int) $plan->diffInMinutes($actual, false);
                }
            }

            $warnings = [];
            if (! $noPlan) {
                if ($lateStart !== null && $lateStart > self::LATE_START_THRESHOLD_MIN) {
                    $warnings[] = 'presence.lateStart';
                }
                if ($planMinutes > 0 && abs($delta) > 0) {
                    $pct = (abs($delta) / max(1, $planMinutes)) * 100;
                    if ($pct > self::HOURS_DIFF_THRESHOLD_PERCENT) {
                        $warnings[] = 'presence.hoursDiff';
                    }
                }
            }

            $rows[] = [
                'date' => $key,
                'plan_minutes' => $planMinutes,
                'actual_minutes' => $actualMinutes,
                'delta_minutes' => $delta,
                'plan_start' => $planStart,
                'actual_start' => $actualStart,
                'late_start_minutes' => $lateStart,
                'warnings' => $warnings,
                'no_plan' => $noPlan,
            ];
        }

        return $rows;
    }

    /**
     * Team-/Org-Aggregation (Rang 38): Summen je Mitarbeiter:in über den
     * Zeitraum — gerechnet aus derselben Tageslogik wie die Personen-Sicht
     * (keine Doppel-Implementierung), damit Summen == Einzelwerte gilt.
     *
     * @param  iterable<int, User>  $users
     * @return array{
     *     rows: list<array{user: User, plan_minutes: int, actual_minutes: int, delta_minutes: int, warnings: int, days_with_plan: int}>,
     *     totals: array{plan_minutes: int, actual_minutes: int, delta_minutes: int, warnings: int},
     * }
     */
    public function presenceSummaryFor(iterable $users, CarbonImmutable $from, CarbonImmutable $to): array {
        $rows = [];
        $totals = ['plan_minutes' => 0, 'actual_minutes' => 0, 'delta_minutes' => 0, 'warnings' => 0];

        foreach ($users as $user) {
            $days = $this->presenceFor($user, $from, $to);

            $row = [
                'user' => $user,
                'plan_minutes' => array_sum(array_column($days, 'plan_minutes')),
                'actual_minutes' => array_sum(array_column($days, 'actual_minutes')),
                'delta_minutes' => array_sum(array_column($days, 'delta_minutes')),
                'warnings' => array_sum(array_map(fn (array $d): int => count($d['warnings']), $days)),
                'days_with_plan' => count(array_filter($days, fn (array $d): bool => ! $d['no_plan'])),
            ];

            $rows[] = $row;
            $totals['plan_minutes'] += $row['plan_minutes'];
            $totals['actual_minutes'] += $row['actual_minutes'];
            $totals['delta_minutes'] += $row['delta_minutes'];
            $totals['warnings'] += $row['warnings'];
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Schicht-Plan/Ist (§2.3, MVP-333): Soll/Ist-Minuten je Schichttyp plus
     * Tages-/Wochen-Buckets für die Verlaufsdarstellung.
     *
     * @param  'day'|'week'  $group  Bucket-Granularität (Tag = Y-m-d, Woche = ISO o-\WW).
     * @return array{
     *     rows: list<array{shift_type_id: int|null, name: string, color: string|null, shifts: int, without_window: int, plan_minutes: int, actual_minutes: int, delta_minutes: int, coverage_pct: float|null}>,
     *     buckets: list<array{key: string, plan_minutes: int, actual_minutes: int, delta_minutes: int}>,
     *     totals: array{shifts: int, plan_minutes: int, actual_minutes: int, delta_minutes: int, coverage_pct: float|null},
     * }
     */
    public function shiftFor(CarbonImmutable $from, CarbonImmutable $to, string $group = 'day'): array {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $shifts = ScheduledShift::query()
            ->visible()
            ->forDateRange($from->toDateString(), $to->toDateString())
            ->with('shiftType')
            ->orderBy('date')
            ->get();

        // Anwesenheiten aller eingeteilten Personen inkl. Folgetag (Übernacht-
        // Schichten reichen in den nächsten Kalendertag hinein).
        $attendances = Attendance::query()
            ->whereIn('user_id', $shifts->pluck('user_id')->unique()->values())
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->addDay()->toDateString())
            ->get()
            ->groupBy(fn (Attendance $a): string => $a->user_id . '|' . ($a->date?->format('Y-m-d') ?? ''));

        /** @var array<int|string, array{shift_type_id: int|null, name: string, color: string|null, shifts: int, without_window: int, plan_minutes: int, actual_minutes: int}> $byType */
        $byType = [];
        /** @var array<string, array{key: string, plan_minutes: int, actual_minutes: int}> $buckets */
        $buckets = [];
        /** @var array<string, true> $daysCounted Fallback ohne Zeitfenster: Tages-Ist je Person+Tag nur einmal. */
        $daysCounted = [];
        $totalPlan = 0;
        $totalActual = 0;

        foreach ($shifts as $shift) {
            $dateStr = $shift->date->format('Y-m-d');
            $start = $shift->resolvedStartTime();
            $end = $shift->resolvedEndTime();

            $plan = 0;
            $actual = 0;
            $withoutWindow = false;

            if ($start !== null && $start !== '' && $end !== null && $end !== '') {
                $winStart = CarbonImmutable::parse($dateStr . ' ' . $start);
                $winEnd = CarbonImmutable::parse($dateStr . ' ' . $end);
                if ($winEnd->lte($winStart)) {
                    $winEnd = $winEnd->addDay(); // Übernacht-Schicht
                }
                $plan = (int) $winStart->diffInMinutes($winEnd);

                foreach ([$dateStr, $shift->date->toImmutable()->addDay()->format('Y-m-d')] as $day) {
                    /** @var Collection<int, Attendance> $dayAttendances */
                    $dayAttendances = $attendances->get($shift->user_id . '|' . $day, new Collection());
                    foreach ($dayAttendances as $attendance) {
                        $actual += $this->overlapMinutes($attendance, $winStart, $winEnd);
                    }
                }
            } else {
                // Ohne Zeitfenster ist kein Soll bestimmbar; als "zugeordnetes
                // Ist" gilt die Tages-Anwesenheit der eingeteilten Person.
                $withoutWindow = true;
                $dayKey = $shift->user_id . '|' . $dateStr;
                if (! isset($daysCounted[$dayKey])) {
                    $daysCounted[$dayKey] = true;
                    /** @var Collection<int, Attendance> $dayAttendances */
                    $dayAttendances = $attendances->get($dayKey, new Collection());
                    $actual = (int) $dayAttendances->sum('duration_minutes');
                }
            }

            $typeKey = $shift->shift_type_id ?? 0;
            if (! isset($byType[$typeKey])) {
                $byType[$typeKey] = [
                    'shift_type_id' => $shift->shift_type_id,
                    'name' => $shift->shiftType->name ?? (string) __('Ohne Schichttyp'),
                    'color' => $shift->shiftType?->color,
                    'shifts' => 0,
                    'without_window' => 0,
                    'plan_minutes' => 0,
                    'actual_minutes' => 0,
                ];
            }
            $byType[$typeKey]['shifts']++;
            $byType[$typeKey]['without_window'] += $withoutWindow ? 1 : 0;
            $byType[$typeKey]['plan_minutes'] += $plan;
            $byType[$typeKey]['actual_minutes'] += $actual;

            $bucketKey = $group === 'week'
                ? $shift->date->toImmutable()->format('o-\WW')
                : $dateStr;
            $buckets[$bucketKey] ??= ['key' => $bucketKey, 'plan_minutes' => 0, 'actual_minutes' => 0];
            $buckets[$bucketKey]['plan_minutes'] += $plan;
            $buckets[$bucketKey]['actual_minutes'] += $actual;

            $totalPlan += $plan;
            $totalActual += $actual;
        }

        $rows = array_values($byType);
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        $rows = array_map(static function (array $row): array {
            $row['delta_minutes'] = $row['actual_minutes'] - $row['plan_minutes'];
            $row['coverage_pct'] = $row['plan_minutes'] > 0
                ? round($row['actual_minutes'] / $row['plan_minutes'] * 100, 1)
                : null;

            return $row;
        }, $rows);

        ksort($buckets);
        $bucketRows = array_map(static function (array $bucket): array {
            $bucket['delta_minutes'] = $bucket['actual_minutes'] - $bucket['plan_minutes'];

            return $bucket;
        }, array_values($buckets));

        return [
            'rows' => $rows,
            'buckets' => $bucketRows,
            'totals' => [
                'shifts' => $shifts->count(),
                'plan_minutes' => $totalPlan,
                'actual_minutes' => $totalActual,
                'delta_minutes' => $totalActual - $totalPlan,
                'coverage_pct' => $totalPlan > 0 ? round($totalActual / $totalPlan * 100, 1) : null,
            ],
        ];
    }

    /**
     * Projekt-Plan/Ist (§2.2, MVP-333): Soll aus `DiaryEntry.planned_minutes`
     * der im Zeitraum überlappenden Aufträge (overlappingDateRange-Scope),
     * Ist aus TimeEntry-Minuten je Projekt; Projekte ohne geplante Aufträge
     * werden als `no_plan` markiert (Konzept: kein Alarm). Zeiten ohne
     * Projektbezug erscheinen als eigene Zeile.
     *
     * @return array{
     *     rows: list<array{project_id: int|null, name: string, customer: string|null, orders: int, planned_orders: int, plan_minutes: int, actual_minutes: int, billable_minutes: int, delta_minutes: int, no_plan: bool}>,
     *     totals: array{plan_minutes: int, actual_minutes: int, billable_minutes: int, delta_minutes: int, projects: int, no_plan_projects: int},
     * }
     */
    public function projectTimeFor(CarbonImmutable $from, CarbonImmutable $to): array {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // toBase(): Global Scopes (Org) bleiben angewandt, Ergebnis sind
        // schlanke stdClass-Zeilen statt Models mit Magie-Attributen.
        $actuals = TimeEntry::query()
            ->whereBetween('date', [$fromDate, $toDate])
            ->groupBy('project_id')
            ->selectRaw('project_id, COALESCE(SUM(minutes), 0) AS total_minutes, COALESCE(SUM(CASE WHEN billable = 1 THEN minutes ELSE 0 END), 0) AS billable_minutes')
            ->toBase()
            ->get()
            ->keyBy(static fn (object $row): int => (int) ($row->project_id ?? 0));

        $plans = DiaryEntry::query()
            ->whereNotNull('project_id')
            ->overlappingDateRange($fromDate, $toDate)
            ->groupBy('project_id')
            ->selectRaw('project_id, COALESCE(SUM(COALESCE(planned_minutes, 0)), 0) AS plan_minutes, COUNT(*) AS orders, SUM(CASE WHEN planned_minutes IS NOT NULL THEN 1 ELSE 0 END) AS planned_orders')
            ->toBase()
            ->get()
            ->keyBy(static fn (object $row): int => (int) $row->project_id);

        $projectIds = $actuals->keys()
            ->merge($plans->keys())
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $projects = Project::query()
            ->with('customer:id,name')
            ->whereIn('id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);

        $rows = [];
        $totals = ['plan_minutes' => 0, 'actual_minutes' => 0, 'billable_minutes' => 0, 'delta_minutes' => 0, 'projects' => 0, 'no_plan_projects' => 0];

        foreach ($projects as $project) {
            $actual = $actuals->get($project->id);
            $plan = $plans->get($project->id);

            $planMinutes = (int) ($plan->plan_minutes ?? 0);
            $actualMinutes = (int) ($actual->total_minutes ?? 0);
            $plannedOrders = (int) ($plan->planned_orders ?? 0);

            $row = [
                'project_id' => (int) $project->id,
                'name' => (string) $project->name,
                'customer' => $project->customer?->name,
                'orders' => (int) ($plan->orders ?? 0),
                'planned_orders' => $plannedOrders,
                'plan_minutes' => $planMinutes,
                'actual_minutes' => $actualMinutes,
                'billable_minutes' => (int) ($actual->billable_minutes ?? 0),
                'delta_minutes' => $actualMinutes - $planMinutes,
                'no_plan' => $plannedOrders === 0,
            ];

            $rows[] = $row;
            $totals['plan_minutes'] += $row['plan_minutes'];
            $totals['actual_minutes'] += $row['actual_minutes'];
            $totals['billable_minutes'] += $row['billable_minutes'];
            $totals['projects']++;
            $totals['no_plan_projects'] += $row['no_plan'] ? 1 : 0;
        }

        // Zeiten ohne Projektbezug: als eigene Zeile ausweisen statt still verwerfen.
        $unassigned = $actuals->get(0);
        if ($unassigned !== null && (int) $unassigned->total_minutes > 0) {
            $actualMinutes = (int) $unassigned->total_minutes;
            $rows[] = [
                'project_id' => null,
                'name' => (string) __('Ohne Projekt'),
                'customer' => null,
                'orders' => 0,
                'planned_orders' => 0,
                'plan_minutes' => 0,
                'actual_minutes' => $actualMinutes,
                'billable_minutes' => (int) $unassigned->billable_minutes,
                'delta_minutes' => $actualMinutes,
                'no_plan' => true,
            ];
            $totals['actual_minutes'] += $actualMinutes;
            $totals['billable_minutes'] += (int) $unassigned->billable_minutes;
        }

        $totals['delta_minutes'] = $totals['actual_minutes'] - $totals['plan_minutes'];

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Standort-Aggregation (MVP-333): Ist-Verteilung der ortsbasiert erfassten
     * Zeiten (abgeschlossene LocationVisits über CustomerGeofence.site_id).
     * Solldaten je Standort existieren im Datenmodell nicht — die Sicht ist
     * bewusst Ist-only, die UI weist die Lücke aus. Besuche an Geofences ohne
     * Standort-Zuordnung erscheinen als eigene Zeile.
     *
     * @return array{
     *     rows: list<array{site_id: int|null, name: string, customer: string|null, visits: int, users: int, actual_minutes: int}>,
     *     totals: array{visits: int, users: int, actual_minutes: int},
     * }
     */
    public function siteFor(CarbonImmutable $from, CarbonImmutable $to): array {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $aggregates = LocationVisit::query()
            ->closed()
            ->join('customer_geofences', 'customer_geofences.id', '=', 'location_visits.customer_geofence_id')
            ->whereBetween('location_visits.entered_at', [$from, $to])
            ->groupBy('customer_geofences.site_id')
            ->selectRaw('customer_geofences.site_id AS site_id, COUNT(*) AS visits, COUNT(DISTINCT location_visits.user_id) AS users, COALESCE(SUM(location_visits.duration_min), 0) AS minutes')
            ->toBase()
            ->get();

        $sites = Site::query()
            ->with('customer:id,name')
            ->whereIn('id', $aggregates->pluck('site_id')->filter()->values())
            ->get(['id', 'name', 'customer_id'])
            ->keyBy('id');

        $rows = [];
        $totals = ['visits' => 0, 'users' => 0, 'actual_minutes' => 0];

        foreach ($aggregates as $aggregate) {
            /** @var Site|null $site */
            $site = $aggregate->site_id !== null ? $sites->get((int) $aggregate->site_id) : null;

            $rows[] = [
                'site_id' => $aggregate->site_id !== null ? (int) $aggregate->site_id : null,
                'name' => $site->name ?? (string) __('Ohne Standort-Zuordnung'),
                'customer' => $site?->customer?->name,
                'visits' => (int) $aggregate->visits,
                'users' => (int) $aggregate->users,
                'actual_minutes' => (int) $aggregate->minutes,
            ];

            $totals['visits'] += (int) $aggregate->visits;
            $totals['actual_minutes'] += (int) $aggregate->minutes;
        }

        usort($rows, static function (array $a, array $b): int {
            // "Ohne Standort-Zuordnung" ans Ende, sonst alphabetisch.
            if (($a['site_id'] === null) !== ($b['site_id'] === null)) {
                return $a['site_id'] === null ? 1 : -1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        // Personen org-weit distinct zählen (Summe je Standort würde doppelt zählen).
        $totals['users'] = (int) LocationVisit::query()
            ->closed()
            ->whereBetween('entered_at', [$from, $to])
            ->distinct()
            ->count('user_id');

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** Überlappung eines Anwesenheitsintervalls mit dem Schichtfenster in Minuten. */
    private function overlapMinutes(Attendance $attendance, CarbonImmutable $winStart, CarbonImmutable $winEnd): int {
        $start = $attendance->started_at?->toImmutable();
        $end = $attendance->ended_at?->toImmutable();
        if ($start === null || $end === null) {
            return 0; // offene/zeitlose Stempelungen sind keinem Fenster zuordenbar
        }

        $overlapStart = $start->gt($winStart) ? $start : $winStart;
        $overlapEnd = $end->lt($winEnd) ? $end : $winEnd;

        return $overlapEnd->gt($overlapStart) ? (int) $overlapStart->diffInMinutes($overlapEnd) : 0;
    }

    /** @param Collection<int, WorkSchedule> $schedules */
    private function scheduleFor(Collection $schedules, CarbonImmutable $date): ?WorkSchedule {
        $dateStr = $date->toDateString();
        foreach ($schedules->reverse() as $s) {
            /** @var \Illuminate\Support\Carbon|null $vf */
            $vf = $s->valid_from;
            /** @var \Illuminate\Support\Carbon|null $vt */
            $vt = $s->valid_to;
            $fromOk = $vf === null || $vf->format('Y-m-d') <= $dateStr;
            $toOk = $vt === null || $vt->format('Y-m-d') >= $dateStr;
            if ($fromOk && $toOk) {
                return $s;
            }
        }

        return null;
    }
}
