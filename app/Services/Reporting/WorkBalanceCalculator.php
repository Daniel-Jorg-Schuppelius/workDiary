<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkBalanceCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\TimeEntry\TimeEntryActivityType;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Attendance;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Aggregates the unified work-time picture for reporting: combines the
 * timesheet targets ({@see FlexCalculator}), recorded attendances and the
 * various TimeEntry activity types into a single per-day summary.
 *
 * Used by the Work-Balance report (monthly / yearly view) to give the user
 * one consistent number for "Soll", "Anwesenheit", "Erfasst" and the
 * resulting balance.
 */
class WorkBalanceCalculator {
    public function __construct(protected FlexCalculator $flex) {
    }

    public function daily(User $user, CarbonInterface $day): DailyBalance {
        $dayStr = $day->toDateString();

        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $dayStr)
            ->where('status', '!=', AttendanceStatus::Cancelled->value)
            ->get();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $dayStr)
            ->get();

        $attendanceMinutes = 0;
        $breakMinutes = 0;
        foreach ($attendances as $a) {
            $breakMinutes += (int) $a->break_minutes_total;
            if ((int) $a->duration_minutes > 0) {
                $attendanceMinutes += (int) $a->duration_minutes;

                continue;
            }
            if ($a->started_at) {
                $end = $a->ended_at ?? CarbonImmutable::now();
                $gross = (int) $a->started_at->diffInMinutes($end, false);
                $attendanceMinutes += max(0, $gross - (int) $a->break_minutes_total);
            }
        }

        $byActivity = [];
        $byKind = [];
        $trackedMinutes = 0;
        $countedKinds = (array) config('timesheet.flex.count_kinds', [TimeEntryKind::Work->value, TimeEntryKind::Travel->value]);
        $excluded = (array) config('timesheet.flex.exclude_activity_types', [TimeEntryActivityType::Break_->value, TimeEntryActivityType::Absence->value]);
        foreach ($entries as $e) {
            $minutes = (int) $e->minutes;
            $activityValue = $e->activity_type->value;
            $byActivity[$activityValue] = ($byActivity[$activityValue] ?? 0) + $minutes;
            $kindValue = $e->kind->value;
            $byKind[$kindValue] = ($byKind[$kindValue] ?? 0) + $minutes;
            if (in_array($kindValue, $countedKinds, true) && ! in_array($activityValue, $excluded, true)) {
                $trackedMinutes += $minutes;
            }
        }

        $target = $this->flex->targetMinutes($user, $day);
        $balance = $trackedMinutes - $target;
        $untracked = max(0, $attendanceMinutes - $trackedMinutes);

        return new DailyBalance(
            date: $day->toDateString(),
            targetMinutes: $target,
            attendanceMinutes: $attendanceMinutes,
            breakMinutes: $breakMinutes,
            trackedMinutes: $trackedMinutes,
            untrackedMinutes: $untracked,
            balanceMinutes: $balance,
            byActivity: $byActivity,
            byKind: $byKind,
        );
    }

    public function range(User $user, CarbonInterface $from, CarbonInterface $to): PeriodBalance {
        $days = [];
        $totalTarget = 0;
        $totalAttendance = 0;
        $totalBreak = 0;
        $totalTracked = 0;
        $totalUntracked = 0;
        $byActivity = [];
        $byKind = [];

        for ($d = CarbonImmutable::parse($from->toDateString()); $d->lte($to); $d = $d->addDay()) {
            $b = $this->daily($user, $d);
            $days[$b->date] = $b;
            $totalTarget += $b->targetMinutes;
            $totalAttendance += $b->attendanceMinutes;
            $totalBreak += $b->breakMinutes;
            $totalTracked += $b->trackedMinutes;
            $totalUntracked += $b->untrackedMinutes;
            foreach ($b->byActivity as $k => $v) {
                $byActivity[$k] = ($byActivity[$k] ?? 0) + $v;
            }
            foreach ($b->byKind as $k => $v) {
                $byKind[$k] = ($byKind[$k] ?? 0) + $v;
            }
        }

        return new PeriodBalance(
            from: $from->toDateString(),
            to: $to->toDateString(),
            targetMinutes: $totalTarget,
            attendanceMinutes: $totalAttendance,
            breakMinutes: $totalBreak,
            trackedMinutes: $totalTracked,
            untrackedMinutes: $totalUntracked,
            balanceMinutes: $totalTracked - $totalTarget,
            byActivity: $byActivity,
            byKind: $byKind,
            days: $days,
        );
    }

    public function month(User $user, int $year, int $month): PeriodBalance {
        $start = CarbonImmutable::create($year, $month, 1)?->startOfMonth() ?? CarbonImmutable::now()->startOfMonth();

        return $this->range($user, $start, $start->endOfMonth());
    }

    public function year(User $user, int $year): PeriodBalance {
        $start = CarbonImmutable::create($year, 1, 1)?->startOfYear() ?? CarbonImmutable::now()->startOfYear();

        return $this->range($user, $start, $start->endOfYear());
    }
}
