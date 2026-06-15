<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceComplianceChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Organization;
use App\Services\Timekeeping\BreakRuleEvaluator;
use Carbon\CarbonImmutable;

/**
 * ArbZG-Compliance-Prüfung auf der TATSÄCHLICH erfassten Arbeitszeit
 * (Attendance/Ist), nicht auf der Dienstplan-Vorausschau (ScheduledShift,
 * vgl. {@see ShiftComplianceService}).
 *
 * BEWUSST PURE: keine DB-Zugriffe. Stempel-Spannen werden je Mitarbeiter
 * injiziert; der Controller lädt/aggregiert die Daten. Damit ist die reine
 * Schwellenprüfung isoliert testbar.
 *
 * DRY — die ArbZG-Schwellen stammen ausschliesslich aus dem Bestand und
 * werden NICHT hier neu definiert:
 *  - Tages-/Wochen-/Ruhezeit-Schwellen: {@see Organization::complianceSettings()}
 *    bzw. {@see Organization::COMPLIANCE_DEFAULTS} (max_hours_day=10,
 *    min_rest_hours=11, max_hours_week=48) — dieselbe Quelle wie die
 *    ScheduledShift-Regeln (MaxDailyHoursRule/RestPeriodRule/MaxWeeklyHoursRule).
 *  - Pflichtpausen: {@see BreakRuleEvaluator} (config timesheet.breaks.rules,
 *    ArbZG §4: 30 min ab 6h, 45 min ab 9h) — dieselbe Quelle wie der
 *    Tagesabschluss (DayClosureValidator::CHECK_BREAK_REQUIRED).
 *  - Tages-Netto (gross − Pausen): identische Logik wie
 *    DayClosureValidator::aggregate().
 *
 * Eingabeformat je Tag:
 *  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>
 *
 * Ausgabe: list<AttendanceComplianceFinding>.
 */
final class AttendanceComplianceChecker {
    public const KIND_MAX_DAILY_HOURS = 'maxDailyHours';

    public const KIND_REST_PERIOD = 'restPeriod';

    public const KIND_BREAK_MISSING = 'breakMissing';

    public const KIND_MAX_WEEKLY_HOURS = 'maxWeeklyHours';

    /**
     * @param  array{mode:string, max_hours_day:int, min_rest_hours:int, max_hours_week:int, max_consecutive_days:int, rules:array<string,bool>}  $settings  z. B. Organization::complianceSettings()
     */
    public function __construct(
        private readonly array $settings,
        private readonly BreakRuleEvaluator $breakRules,
    ) {}

    /** Bequemer Konstruktor mit den Compliance-Settings einer Organisation. */
    public static function forOrganization(?Organization $organization): self {
        $settings = $organization
            ? $organization->complianceSettings()
            : Organization::COMPLIANCE_DEFAULTS;

        return new self($settings, app(BreakRuleEvaluator::class));
    }

    /** Compliance global deaktiviert? Dann werden keine Verstöße ausgewiesen. */
    public function enabled(): bool {
        return $this->settings['mode'] !== Organization::COMPLIANCE_OFF;
    }

    /**
     * Prüft die Ist-Arbeitszeit EINES Mitarbeiters über einen Zeitraum.
     *
     * @param  array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>>  $attendancesByDate  Stempel-Spannen je Kalendertag (Y-m-d)
     * @return list<AttendanceComplianceFinding>
     */
    public function checkUser(int $userId, array $attendancesByDate, ?CarbonImmutable $now = null): array {
        if (! $this->enabled()) {
            return [];
        }
        $now ??= CarbonImmutable::now();

        $findings = [];

        // Tages-Aggregate (Netto/Brutto/Pausen, erste Start- und letzte
        // Endzeit für die Ruhezeit) je Kalendertag berechnen.
        $days = [];
        foreach ($attendancesByDate as $date => $spans) {
            $agg = $this->aggregateDay($spans, $now);
            if ($agg['gross'] <= 0) {
                continue;
            }
            $days[$date] = $agg;

            // 1. Tägliche Höchstarbeitszeit (Netto > max_hours_day, Standard 10h).
            foreach ($this->checkMaxDailyHours($userId, $date, $agg) as $f) {
                $findings[] = $f;
            }

            // 2. Pflichtpause (ArbZG §4, via BreakRuleEvaluator).
            foreach ($this->checkBreak($userId, $date, $agg) as $f) {
                $findings[] = $f;
            }
        }

        // 3. Ruhezeit zwischen zwei Arbeitstagen (< min_rest_hours, Standard 11h).
        foreach ($this->checkRestPeriods($userId, $days) as $f) {
            $findings[] = $f;
        }

        // 4. Wöchentliche Höchstarbeitszeit (Ø > max_hours_week, Standard 48h).
        foreach ($this->checkWeeklyHours($userId, $days) as $f) {
            $findings[] = $f;
        }

        usort($findings, static fn(AttendanceComplianceFinding $a, AttendanceComplianceFinding $b): int => [$a->date, $a->kind] <=> [$b->date, $b->kind]);

        return $findings;
    }

    // ── Einzelprüfungen ──────────────────────────────────────────────────

    /**
     * @param  array{gross:int, breaks:int, net:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}  $agg
     * @return list<AttendanceComplianceFinding>
     */
    private function checkMaxDailyHours(int $userId, string $date, array $agg): array {
        $maxMinutes = $this->maxDailyMinutes();
        if ($agg['net'] <= $maxMinutes) {
            return [];
        }

        return [new AttendanceComplianceFinding(
            userId: $userId,
            date: $date,
            kind: self::KIND_MAX_DAILY_HOURS,
            severity: AttendanceComplianceFinding::SEVERITY_ERROR,
            value: $agg['net'],
            threshold: $maxMinutes,
        )];
    }

    /**
     * @param  array{gross:int, breaks:int, net:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}  $agg
     * @return list<AttendanceComplianceFinding>
     */
    private function checkBreak(int $userId, string $date, array $agg): array {
        // requiredMinutes() arbeitet auf der Brutto-Arbeitszeit (wie Tagesabschluss).
        $required = $this->breakRules->requiredMinutes($agg['gross']);
        if ($required <= 0 || $agg['breaks'] >= $required) {
            return [];
        }

        return [new AttendanceComplianceFinding(
            userId: $userId,
            date: $date,
            kind: self::KIND_BREAK_MISSING,
            severity: AttendanceComplianceFinding::SEVERITY_ERROR,
            value: $agg['breaks'],
            threshold: $required,
        )];
    }

    /**
     * @param  array<string, array{gross:int, breaks:int, net:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkRestPeriods(int $userId, array $days): array {
        $minRestMinutes = (int) $this->settings['min_rest_hours'] * 60;
        ksort($days);
        $dates = array_keys($days);

        $findings = [];
        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $prevEnd = $days[$dates[$i - 1]]['last_end'];
            $currStart = $days[$dates[$i]]['first_start'];
            if ($prevEnd === null || $currStart === null || $currStart->lessThanOrEqualTo($prevEnd)) {
                continue;
            }
            $gapMinutes = (int) $prevEnd->diffInMinutes($currStart, false);
            if ($gapMinutes < $minRestMinutes) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $dates[$i],
                    kind: self::KIND_REST_PERIOD,
                    severity: AttendanceComplianceFinding::SEVERITY_ERROR,
                    value: $gapMinutes,
                    threshold: $minRestMinutes,
                );
            }
        }

        return $findings;
    }

    /**
     * Wöchentliche Höchstarbeitszeit als Hinweis (ISO-Woche; ArbZG §3 bezieht
     * sich auf den Durchschnitt über den Bezugszeitraum — hier je ISO-Woche
     * summiert, analog MaxWeeklyHoursRule).
     *
     * @param  array<string, array{gross:int, breaks:int, net:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}>  $days
     * @return list<AttendanceComplianceFinding>
     */
    private function checkWeeklyHours(int $userId, array $days): array {
        $maxMinutes = (int) $this->settings['max_hours_week'] * 60;

        /** @var array<string, array{minutes:int, week_end:string}> $byWeek */
        $byWeek = [];
        foreach ($days as $date => $agg) {
            $d = CarbonImmutable::parse($date);
            $key = $d->isoFormat('GGGG-[W]WW');
            if (! isset($byWeek[$key])) {
                $byWeek[$key] = ['minutes' => 0, 'week_end' => $d->endOfWeek()->toDateString()];
            }
            $byWeek[$key]['minutes'] += $agg['net'];
        }

        $findings = [];
        foreach ($byWeek as $week) {
            if ($week['minutes'] > $maxMinutes) {
                $findings[] = new AttendanceComplianceFinding(
                    userId: $userId,
                    date: $week['week_end'],
                    kind: self::KIND_MAX_WEEKLY_HOURS,
                    severity: AttendanceComplianceFinding::SEVERITY_WARNING,
                    value: $week['minutes'],
                    threshold: $maxMinutes,
                );
            }
        }

        return $findings;
    }

    // ── Aggregation ──────────────────────────────────────────────────────

    /** Maximale Tages-Netto-Arbeitszeit in Minuten (Standard 10h, ArbZG §3). */
    private function maxDailyMinutes(): int {
        return (int) $this->settings['max_hours_day'] * 60;
    }

    /**
     * Brutto/Pausen/Netto eines Kalendertags — identische Rechnung wie
     * DayClosureValidator::aggregate() (Netto = max(0, brutto − Pausen)).
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>  $spans
     * @return array{gross:int, breaks:int, net:int, first_start: ?CarbonImmutable, last_end: ?CarbonImmutable}
     */
    private function aggregateDay(array $spans, CarbonImmutable $now): array {
        $gross = 0;
        $breaks = 0;
        $firstStart = null;
        $lastEnd = null;

        foreach ($spans as $s) {
            $start = $s['started_at'];
            $end = $s['ended_at'] ?? ($start->lessThan($now) ? $now : $start);
            $gross += max(0, (int) $start->diffInMinutes($end, false));
            $breaks += max(0, $s['break_minutes']);

            if ($firstStart === null || $start->lessThan($firstStart)) {
                $firstStart = $start;
            }
            if ($lastEnd === null || $end->greaterThan($lastEnd)) {
                $lastEnd = $end;
            }
        }

        return [
            'gross' => $gross,
            'breaks' => $breaks,
            'net' => max(0, $gross - $breaks),
            'first_start' => $firstStart,
            'last_end' => $lastEnd,
        ];
    }
}
