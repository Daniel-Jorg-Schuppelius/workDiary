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

use App\Models\{Attendance, User, WorkSchedule};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Plan/Ist-Report Builder (MVP-018, ../WorkDiary-Architecture/plan-ist-abgleich.md).
 *
 * Liefert Aggregate für drei Ebenen — initial nur Anwesenheits-Ebene
 * implementiert (§2.1). Projektzeit (§2.2) und Schicht (§2.3) folgen
 * in nachgelagerten Iterationen.
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
