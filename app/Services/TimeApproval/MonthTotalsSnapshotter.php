<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthTotalsSnapshotter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\{Attendance, User};
use App\Services\Flextime\FlexCalculator;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Erzeugt den unveränderlichen Totals-Snapshot einer Monatsfreigabe
 * (MVP-016, ../WorkDiary-Architecture/monatsfreigabe.md §3). Wird beim Übergang draft→submitted
 * und beim Übergang submitted→approved erneut gebaut, damit der zuletzt
 * eingefrorene Zustand exakt das ist, was genehmigt wurde.
 *
 * Datenquellen:
 *  - {@see FlexCalculator::monthlyBalance()} für Soll-/Ist-/Saldo-Minuten und
 *    den Tagessummen-Bucket (target/actual/balance pro ISO-Datum).
 *  - {@see Attendance} für die strukturellen Tageszählungen (offen/abgeschlossen).
 *  - {@see Vacation} (falls vorhanden) für die Urlaubs-Tage-Auswertung.
 *
 * Die Struktur ist bewusst flach gehalten; sie wird so wie sie ist als
 * JSON in `month_closures.totals` abgelegt und ist Quelle der Wahrheit für
 * Exporte (MVP-019) und Inbox-Anzeigen (MVP-016 §6).
 */
class MonthTotalsSnapshotter {
    public function __construct(private readonly FlexCalculator $flex) {}

    /**
     * Baut den Snapshot inkl. Zähler/Warnungs-Counts.
     *
     * @return array{
     *     period: array{year:int, month:int, days_total:int, working_days:int},
     *     minutes: array{target:int, actual:int, balance:int, attendance:int, non_attendance:int},
     *     days: array{with_attendance:int, closed:int, open:int, vacation:int, sick:int, holiday:int},
     *     warnings: array{count:int, blocking:int},
     *     daily: array<string, array{target:int, actual:int, balance:int}>,
     *     generated_at: string
     * }
     */
    public function build(User $user, int $year, int $month): array {
        if ($year < 1900 || $year > 2999 || $month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Ungültige Periode {$year}-{$month}.");
        }
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-01 00:00:00', $year, $month));
        if (! $start instanceof CarbonImmutable) {
            throw new \InvalidArgumentException("Ungültige Periode {$year}-{$month}.");
        }
        $end = $start->endOfMonth();

        $monthly = $this->flex->monthlyBalance($user, $year, $month);

        $daysTotal = (int) $start->daysInMonth;
        $workingDays = 0;
        $daily = [];

        // Vollaudit 2026-07 (N5): sick/holiday/vacation aus den Tagesdaten des
        // FlexCalculator bzw. den Krankmeldungen zählen — auf den Monat geclippt,
        // gezählt in Werktagen (Mo–Fr ohne Feiertage, Semantik wie MVP-413).
        $sickRanges = \App\Models\SickLeave::query()
            ->where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        $vacationDays = 0;
        $sickDays = 0;
        $holidayDays = 0;
        foreach ($monthly['days'] as $iso => $row) {
            $daily[$iso] = [
                'target' => (int) $row['target'],
                'actual' => (int) $row['actual'],
                'balance' => (int) $row['balance'],
            ];
            if ((int) $row['target'] > 0) {
                $workingDays++;
            }

            $isHoliday = (bool) $row['is_holiday'];
            if ($isHoliday) {
                $holidayDays++;
            }
            $day = CarbonImmutable::parse($iso);
            if ($day->isWeekend() || $isHoliday) {
                continue;
            }
            if ((bool) $row['is_vacation']) {
                $vacationDays++;
            }
            if ($sickRanges->contains(fn($s): bool => $s->start_date->toDateString() <= $iso && $s->end_date->toDateString() >= $iso)) {
                $sickDays++;
            }
        }

        // Strukturelle Tageszählungen aus Attendance-Status.
        $attendances = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', DateRange::days($start, $end))
            ->get(['date', 'status', 'duration_minutes']);

        $datesWithAttendance = [];
        $openDates = [];
        $closedDates = [];
        $attendanceMinutes = 0;

        foreach ($attendances as $a) {
            /** @var Attendance $a */
            $iso = $a->date?->toDateString();
            if ($iso === null) {
                continue;
            }
            $datesWithAttendance[$iso] = true;
            $attendanceMinutes += (int) $a->duration_minutes;

            if ($a->status === AttendanceStatus::Open) {
                $openDates[$iso] = true;
            } else {
                $closedDates[$iso] = true;
            }
        }

        // Offen sticht: ein Tag mit irgendeiner offenen Anwesenheit zählt als offen.
        $openCount = count($openDates);
        $closedCount = count(array_diff_key($closedDates, $openDates));

        $target = (int) $monthly['target'];
        $actual = (int) $monthly['actual'];
        $balance = (int) $monthly['balance'];
        $nonAttendance = max(0, $actual - $attendanceMinutes);

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'days_total' => $daysTotal,
                'working_days' => $workingDays,
            ],
            'minutes' => [
                'target' => $target,
                'actual' => $actual,
                'balance' => $balance,
                'attendance' => $attendanceMinutes,
                'non_attendance' => $nonAttendance,
            ],
            'days' => [
                'with_attendance' => count($datesWithAttendance),
                'closed' => $closedCount,
                'open' => $openCount,
                'vacation' => $vacationDays,
                'sick' => $sickDays,
                'holiday' => $holidayDays,
            ],
            'warnings' => [
                'count' => $openCount,
                'blocking' => $openCount,
            ],
            'daily' => $daily,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Tageszählungen für die strukturierten Spalten in `month_closures`
     * (nicht der vollständige Snapshot).
     *
     * @return array{days_total:int, days_with_attendance:int, days_closed:int, days_open:int, warnings_count:int}
     */
    public function counts(User $user, int $year, int $month): array {
        $snap = $this->build($user, $year, $month);

        return [
            'days_total' => $snap['period']['days_total'],
            'days_with_attendance' => $snap['days']['with_attendance'],
            'days_closed' => $snap['days']['closed'],
            'days_open' => $snap['days']['open'],
            'warnings_count' => $snap['warnings']['count'],
        ];
    }
}
