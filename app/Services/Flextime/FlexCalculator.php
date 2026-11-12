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

use App\Enums\Vacation\VacationStatus;
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Platform\User;
use App\Models\Time\{Attendance, FlexBalance, TimeEntry};
use App\Services\Calendar\HolidayService;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\{CarbonImmutable, CarbonInterface};

class FlexCalculator {
    public function __construct(protected WorkScheduleResolver $resolver, protected HolidayService $holidays) {}

    /**
     * Tagessoll in Minuten (0 wenn Feiertag, Wochenende, Urlaub oder Krankheit —
     * wer krank ist, schuldet keine Arbeit; MVP-1098).
     */
    public function targetMinutes(User $user, CarbonInterface $day): int {
        if ($this->isHoliday($day) || $this->isVacation($user, $day) || $this->isSick($user, $day)) {
            return 0;
        }
        $schedule = $this->resolver->for($user, $day);

        return $schedule->targetMinutesForWeekday($day->dayOfWeekIso);
    }

    /**
     * Tage ohne Soll je Nutzer nach denselben Regeln wie {@see targetMinutes()}
     * — gebündelt für Auswertungen über viele Nutzer und Tage.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, true>> [user_id][Y-m-d]
     */
    public function daysWithoutTarget(array $userIds, CarbonInterface $from, CarbonInterface $to): array {
        $first = CarbonImmutable::parse($from->toDateString());
        $last = CarbonImmutable::parse($to->toDateString());

        $holidays = [];
        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            if ($this->isHoliday($day)) {
                $holidays[$day->toDateString()] = true;
            }
        }
        $days = array_fill_keys($userIds, $holidays);

        $absences = Vacation::query()
            ->whereIn('user_id', $userIds)
            ->where('status', VacationStatus::Approved)
            ->where('start_date', '<', DateRange::dayAfter($last))
            ->where('end_date', '>=', $first->toDateString())
            ->get(['user_id', 'start_date', 'end_date'])
            ->concat(SickLeave::query()
                ->whereIn('user_id', $userIds)
                ->whereNull('cancelled_at')
                ->where('start_date', '<', DateRange::dayAfter($last))
                ->where('end_date', '>=', $first->toDateString())
                ->get(['user_id', 'start_date', 'end_date']));
        foreach ($absences as $absence) {
            $start = CarbonImmutable::parse($absence->start_date->toDateString())->max($first);
            $end = CarbonImmutable::parse($absence->end_date->toDateString())->min($last);
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $days[(int) $absence->user_id][$day->toDateString()] = true;
            }
        }

        return $days;
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
            ->countedAsWork()
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
    public function monthlyBalance(User $user, int $year, int $month, ?CarbonInterface $through = null): array {
        $start = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        if ($through !== null) {
            $end = $end->min(CarbonImmutable::parse($through->toDateString())->endOfDay());
        }

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

    /**
     * Monat des Arbeitszeitkontos (MVP-1627): eine Rechnung für Seite, API,
     * Kachel und Terminal. Soll und Ist zählen nur abgeschlossene Tage (bis
     * gestern), sonst stünde im laufenden Monat das Soll der Restwochen schon im
     * Saldo; die Tagesliste bleibt vollständig. `account` ist der Kontostand mit
     * dem Übertrag des Vormonats.
     *
     * @return array{target:int, actual:int, balance:int, carry:int, account:int, settled_through:?string, days:array<string, array{target:int, actual:int, balance:int, is_holiday:bool, is_vacation:bool, holiday_name:?string}>}
     */
    public function accountMonth(User $user, int $year, int $month): array {
        $full = $this->monthlyBalance($user, $year, $month);
        $yesterday = $this->today($user)->subDay()->toDateString();

        $target = 0;
        $actual = 0;
        foreach ($full['days'] as $iso => $day) {
            if ($iso <= $yesterday) {
                $target += $day['target'];
                $actual += $day['actual'];
            }
        }

        $previous = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->subMonth();
        $carry = (int) FlexBalance::query()
            ->where('user_id', $user->id)
            ->where('year', $previous->year)
            ->where('month', $previous->month)
            ->value('balance_minutes');
        $lastDay = (string) array_key_last($full['days']);

        return [
            'target' => $target,
            'actual' => $actual,
            'balance' => $actual - $target,
            'carry' => $carry,
            'account' => $carry + $actual - $target,
            // Gesetzt, solange der Monat nicht abgeschlossen ist.
            'settled_through' => $lastDay !== '' && $lastDay > $yesterday ? $yesterday : null,
            'days' => $full['days'],
        ];
    }

    /**
     * Schreibt den Monatssaldo (MVP-1550). `balance_minutes` ist der laufende
     * Kontostand aus {@see accountMonth()}. Ein freigegebener Monat ist
     * eingefroren (`locked`, MVP-1627): ihn schreibt nur die Freigabe
     * (`$lock = true`) oder die Wiedereröffnung (`$lock = false`) neu.
     */
    public function recompute(User $user, int $year, int $month, ?bool $lock = null): FlexBalance {
        $existing = FlexBalance::query()->where('user_id', $user->id)->where('year', $year)->where('month', $month)->first();
        if ($existing instanceof FlexBalance && $existing->locked && $lock === null) {
            return $existing;
        }

        $b = $this->accountMonth($user, $year, $month);

        return FlexBalance::query()->updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            [
                'target_minutes' => $b['target'],
                'actual_minutes' => $b['actual'],
                'carry_over_minutes' => $b['carry'],
                'balance_minutes' => $b['account'],
                'locked' => $lock ?? false,
                'computed_at' => now(),
            ],
        );
    }

    /**
     * Rechnet ab dem Monat lückenlos bis zum laufenden Monat neu, damit der
     * Übertrag der Folgemonate stimmt. Ohne Sollführung (Vertrauensarbeitszeit)
     * gibt es kein Zeitkonto und keine Zeile. `$lockFirst` setzt bzw. löst die
     * Sperre des ersten Monats (Monatsfreigabe, Wiedereröffnung).
     *
     * @return int Anzahl geschriebener Monate
     */
    public function recomputeFrom(User $user, int $year, int $month, ?bool $lockFirst = null): int {
        $current = $this->today($user)->startOfMonth();
        $written = 0;
        $first = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();
        for ($cursor = $first; $cursor->lte($current); $cursor = $cursor->addMonth()) {
            $lastDay = $cursor->endOfMonth()->min($current->endOfMonth());
            if (! $this->resolver->for($user, $lastDay)->tracksTarget()) {
                continue;
            }
            $this->recompute($user, $cursor->year, $cursor->month, $cursor->equalTo($first) ? $lockFirst : null);
            $written++;
        }

        return $written;
    }

    /** Kalendertag der Organisation als reines Datum — die Monatsrechnung vergleicht Daten, keine Zeitpunkte. */
    private function today(User $user): CarbonImmutable {
        $organization = $user->organization;

        return CarbonImmutable::parse(CarbonImmutable::now($organization !== null ? Tz::ofOrganization($organization) : Tz::current())->toDateString());
    }

    protected function isHoliday(CarbonInterface $day): bool {
        return $this->holidays->isHoliday($day);
    }

    protected function isSick(User $user, CarbonInterface $day): bool {
        return SickLeave::query()
            ->where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->where('start_date', '<', DateRange::dayAfter($day))
            ->where('end_date', '>=', $day->toDateString())
            ->exists();
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
