<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Flextime;

use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Enums\Vacation\VacationStatus;
use App\Models\{Attendance, FlexBalance, TimeEntry, User, Vacation};
use App\Services\HolidayService;
use Carbon\{CarbonImmutable, CarbonInterface};

class FlexCalculator {
    public function __construct(protected WorkScheduleResolver $resolver, protected HolidayService $holidays) {}

    /**
     * Tagessoll in Minuten (0 wenn Feiertag, Wochenende oder Urlaub).
     */
    public function targetMinutes(User $user, CarbonInterface $day): int {
        if ($this->isHoliday($day) || $this->isVacation($user, $day)) {
            return 0;
        }
        $schedule = $this->resolver->for($user, $day);

        return $schedule->targetMinutesForWeekday($day->dayOfWeekIso);
    }

    public function actualMinutes(User $user, CarbonInterface $day): int {
        // 1. Stempelzeiten (Attendance) sind primär: geschlossene liefern pausenbereinigte `duration_minutes`,
        //    offene (heute laufend) werden live hochgerechnet.
        $attendanceMinutes = $this->attendanceMinutes($user, $day);

        // 2. Zusätzlich manuelle TimeEntries OHNE Attendance-Bezug; an eine Attendance gehängte würden doppelt
        //    zählen und werden ausgeschlossen.
        $entryMinutes = (int) TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->whereNull('attendance_id')
            ->whereIn('kind', $this->countedKinds())
            ->whereNotIn('activity_type', $this->excludedActivityTypes())
            ->sum('minutes');

        return $attendanceMinutes + $entryMinutes;
    }

    /**
     * Summe der Stempelzeiten (Anwesenheiten) für einen Tag in Minuten.
     * Offene Stempelungen werden bis "jetzt" hochgerechnet, abzüglich der
     * bereits eingetragenen Pausenminuten.
     */
    public function attendanceMinutes(User $user, CarbonInterface $day): int {
        $rows = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $day->toDateString())
            ->get(['started_at', 'ended_at', 'duration_minutes', 'break_minutes_auto', 'break_minutes_manual']);

        $sum = 0;
        $now = CarbonImmutable::now();
        foreach ($rows as $a) {
            if ($a->ended_at !== null) {
                $sum += (int) $a->duration_minutes;
                continue;
            }
            // Offene Stempelung: live bis jetzt rechnen (nur wenn der Start in der Vergangenheit liegt).
            if ($a->started_at !== null && $a->started_at->lessThan($now)) {
                $gross = (int) $a->started_at->diffInMinutes($now, false);
                $breaks = (int) ($a->break_minutes_auto ?? 0) + (int) ($a->break_minutes_manual ?? 0);
                $sum += max(0, $gross - $breaks);
            }
        }

        return $sum;
    }

    /**
     * @return array<int, string> TimeEntry kinds that count toward Ist-Arbeitszeit.
     */
    protected function countedKinds(): array {
        $kinds = (array) config('timesheet.flex.count_kinds', [TimeEntryKind::Work->value, TimeEntryKind::Travel->value]);

        return array_values(array_map('strval', $kinds));
    }

    /**
     * @return array<int, string> activity_types that must never count (Pausen, Abwesenheit).
     */
    protected function excludedActivityTypes(): array {
        $excl = (array) config('timesheet.flex.exclude_activity_types', [TimeEntryActivityType::Break_->value, TimeEntryActivityType::Absence->value]);

        return array_values(array_map('strval', $excl));
    }

    /**
     * @return array{target:int, actual:int, balance:int}
     */
    public function dailyBalance(User $user, CarbonInterface $day): array {
        $target = $this->targetMinutes($user, $day);
        $actual = $this->actualMinutes($user, $day);

        return [
            'target' => $target,
            'actual' => $actual,
            'balance' => $actual - $target,
        ];
    }

    /**
     * @return array{target:int, actual:int, balance:int, days:array<string, array{target:int, actual:int, balance:int, is_holiday:bool, is_vacation:bool, holiday_name:?string}>}
     */
    public function monthlyBalance(User $user, int $year, int $month): array {
        $start = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        // Feiertage einmalig für den Zeitraum vorladen (statt pro Tag).
        $holidayMap = $this->holidayMap($year);

        $target = 0;
        $actual = 0;
        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $iso = $day->toDateString();
            $holidayName = $holidayMap[$iso] ?? null;
            $isHoliday = $holidayName !== null;
            $isVacation = $this->isVacation($user, $day);

            $b = $this->dailyBalance($user, $day);
            $b['is_holiday'] = $isHoliday;
            $b['is_vacation'] = $isVacation;
            $b['holiday_name'] = $holidayName;

            $target += $b['target'];
            $actual += $b['actual'];
            $days[$iso] = $b;
        }

        return [
            'target' => $target,
            'actual' => $actual,
            'balance' => $actual - $target,
            'days' => $days,
        ];
    }

    /**
     * Liefert eine Map Y-m-d => Feiertagsname für das gegebene Jahr.
     * Nutzt HolidayService (Yasumi + eigene Holiday-Tabelle).
     *
     * @return array<string, string>
     */
    protected function holidayMap(int $year): array {
        return $this->holidays->forYear($year);
    }

    public function recompute(User $user, int $year, int $month): FlexBalance {
        $b = $this->monthlyBalance($user, $year, $month);

        return FlexBalance::query()->updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            [
                'target_minutes' => $b['target'],
                'actual_minutes' => $b['actual'],
                'balance_minutes' => $b['balance'],
                'computed_at' => now(),
            ],
        );
    }

    protected function isHoliday(CarbonInterface $day): bool {
        return $this->holidays->isHoliday($day);
    }

    protected function isVacation(User $user, CarbonInterface $day): bool {
        return Vacation::query()
            ->where('user_id', $user->id)
            ->where('status', VacationStatus::Approved)
            ->whereDate('start_date', '<=', $day->toDateString())
            ->whereDate('end_date', '>=', $day->toDateString())
            ->exists();
    }
}
