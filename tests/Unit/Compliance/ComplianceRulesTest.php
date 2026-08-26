<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceRulesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Compliance;

use App\Models\Organization;
use App\Services\Compliance\{AttendanceComplianceChecker, AttendanceComplianceFinding};
use App\Services\Timekeeping\BreakRuleEvaluator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Feature 131 (MVP-695/696): MiLoG-Erfassungsfrist und ArbZG-Vollregelwerk
 * (§3 6-Monats-Durchschnitt, §6 Nachtarbeit, §11 Ersatzruhetag/freie
 * Sonntage) — deterministische Szenarien mit explizitem `now`.
 */
class ComplianceRulesTest extends TestCase {
    private function checker(): AttendanceComplianceChecker {
        return new AttendanceComplianceChecker(Organization::COMPLIANCE_DEFAULTS, new BreakRuleEvaluator);
    }

    /**
     * @return array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int, recorded_at?: ?CarbonImmutable}
     */
    private function span(string $start, ?string $end, int $break = 0, ?string $recorded = null): array {
        $span = [
            'started_at' => CarbonImmutable::parse($start),
            'ended_at' => $end === null ? null : CarbonImmutable::parse($end),
            'break_minutes' => $break,
        ];
        if ($recorded !== null) {
            $span['recorded_at'] = CarbonImmutable::parse($recorded);
        }

        return $span;
    }

    /**
     * @param  list<AttendanceComplianceFinding>  $findings
     * @return list<AttendanceComplianceFinding>
     */
    private function findingsOf(array $findings, string $kind): array {
        return array_values(array_filter($findings, static fn(AttendanceComplianceFinding $f): bool => $f->kind === $kind));
    }

    // ── MVP-695: MiLoG §17 — 7-Tage-Erfassungsfrist ─────────────────────

    public function test_recording_more_than_seven_days_after_work_is_a_violation(): void {
        // Leistung 01.06., erfasst 09.06. → Verzug 8 Kalendertage > 7.
        $findings = $this->checker()->checkUser(1, [
            '2026-06-01' => [$this->span('2026-06-01 08:00', '2026-06-01 12:00', 0, '2026-06-09 10:00')],
        ], CarbonImmutable::parse('2026-06-15'));

        $late = $this->findingsOf($findings, AttendanceComplianceChecker::KIND_LATE_RECORDING);
        $this->assertCount(1, $late);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $late[0]->severity);
        $this->assertSame(8, $late[0]->value);
        $this->assertSame(7, $late[0]->threshold);
    }

    public function test_recording_within_seven_days_is_fine(): void {
        $findings = $this->checker()->checkUser(1, [
            '2026-06-01' => [$this->span('2026-06-01 08:00', '2026-06-01 12:00', 0, '2026-06-08 23:00')],
        ], CarbonImmutable::parse('2026-06-15'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_LATE_RECORDING));
    }

    public function test_spans_without_recording_timestamp_are_not_flagged(): void {
        $findings = $this->checker()->checkUser(1, [
            '2026-06-01' => [$this->span('2026-06-01 08:00', '2026-06-01 12:00')],
        ], CarbonImmutable::parse('2026-06-15'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_LATE_RECORDING));
    }

    // ── MVP-696: ArbZG §3 S. 2 — 6-Monats-Durchschnitt ──────────────────

    /**
     * @param  int  $weeks  Wochen ab Montag 2026-01-05
     * @return array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>>
     */
    private function sixDayWeeks(int $weeks, string $endTime): array {
        $byDate = [];
        $monday = CarbonImmutable::parse('2026-01-05'); // Montag
        for ($w = 0; $w < $weeks; $w++) {
            for ($d = 0; $d < 6; $d++) { // Mo–Sa
                $day = $monday->addWeeks($w)->addDays($d)->toDateString();
                $byDate[$day] = [$this->span("$day 09:00", "$day $endTime", 45)];
            }
        }

        return $byDate;
    }

    public function test_sustained_nine_hour_days_tip_the_six_month_average(): void {
        // 5 Wochen × 6 Tage × 9 h netto: Ø je Werktag (Mo–Sa) = 540 min > 480.
        $findings = $this->checker()->checkUser(1, $this->sixDayWeeks(5, '18:45'), CarbonImmutable::parse('2026-03-01'));

        $avg = $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SIX_MONTH_AVERAGE);
        $this->assertNotSame([], $avg);
        $last = end($avg);
        $this->assertSame('2026-02-08', $last->date); // Wochenende der letzten Woche
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_WARNING, $last->severity);
        $this->assertSame(540, $last->value);
        $this->assertSame(480, $last->threshold);
    }

    public function test_eight_hour_average_is_fine(): void {
        // 6 Tage × 8 h netto = Ø exakt 480 min (Grenze, kein Befund).
        $findings = $this->checker()->checkUser(1, $this->sixDayWeeks(5, '17:45'), CarbonImmutable::parse('2026-03-01'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SIX_MONTH_AVERAGE));
    }

    public function test_less_than_four_weeks_of_data_is_not_judged(): void {
        // 2 Wochen Abdeckung < 28 Tage Mindestfenster — kein Durchschnitts-Befund.
        $findings = $this->checker()->checkUser(1, $this->sixDayWeeks(2, '19:45'), CarbonImmutable::parse('2026-03-01'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SIX_MONTH_AVERAGE));
    }

    // ── MVP-696: ArbZG §6 — Nachtarbeit ─────────────────────────────────

    public function test_night_work_above_eight_hours_is_flagged(): void {
        // 22:00–07:30 (−45 Pause) = 525 min netto; 23–6 voll enthalten (420 min Nachtzeit).
        $findings = $this->checker()->checkUser(1, [
            '2026-03-10' => [$this->span('2026-03-10 22:00', '2026-03-11 07:30', 45)],
        ], CarbonImmutable::parse('2026-03-20'));

        $night = $this->findingsOf($findings, AttendanceComplianceChecker::KIND_NIGHT_WORK);
        $this->assertCount(1, $night);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_WARNING, $night[0]->severity);
        $this->assertSame(525, $night[0]->value);
        $this->assertSame(480, $night[0]->threshold);
    }

    public function test_day_work_above_eight_hours_is_no_night_finding(): void {
        // 06:00–16:00 liegt komplett außerhalb der Nachtzeit 23–6.
        $findings = $this->checker()->checkUser(1, [
            '2026-03-10' => [$this->span('2026-03-10 06:00', '2026-03-10 16:00', 45)],
        ], CarbonImmutable::parse('2026-03-20'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_NIGHT_WORK));
    }

    public function test_short_night_shift_is_no_finding(): void {
        // 23:30–05:30 = 360 min netto ≤ 480 — Nachtarbeit, aber unter 8 h.
        $findings = $this->checker()->checkUser(1, [
            '2026-03-10' => [$this->span('2026-03-10 23:30', '2026-03-11 05:30', 0)],
        ], CarbonImmutable::parse('2026-03-20'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_NIGHT_WORK));
    }

    // ── MVP-696: ArbZG §11 Abs. 3 — Ersatzruhetag ───────────────────────

    /**
     * Sonntag 2026-03-01 gearbeitet + alle Werktage des Fensters belegt;
     * $freeDate lässt optional einen Werktag frei.
     *
     * @return array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>>
     */
    private function sundayWorkWindow(?string $freeDate = null): array {
        $byDate = [
            '2026-03-01' => [$this->span('2026-03-01 08:00', '2026-03-01 12:00')],
        ];
        for ($d = CarbonImmutable::parse('2026-03-02'); $d->lessThanOrEqualTo(CarbonImmutable::parse('2026-03-14')); $d = $d->addDay()) {
            $ds = $d->toDateString();
            if ($d->isSunday() || $ds === $freeDate) {
                continue;
            }
            $byDate[$ds] = [$this->span("$ds 08:00", "$ds 15:00", 30)];
        }

        return $byDate;
    }

    public function test_sunday_work_without_substitute_rest_day_is_a_violation(): void {
        $findings = $this->checker()->checkUser(1, $this->sundayWorkWindow(), CarbonImmutable::parse('2026-04-01'));

        $rest = $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SUBSTITUTE_REST_DAY);
        $this->assertCount(1, $rest);
        $this->assertSame('2026-03-01', $rest[0]->date);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $rest[0]->severity);
    }

    public function test_free_weekday_in_window_counts_as_substitute_rest_day(): void {
        $findings = $this->checker()->checkUser(1, $this->sundayWorkWindow('2026-03-06'), CarbonImmutable::parse('2026-04-01'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SUBSTITUTE_REST_DAY));
    }

    public function test_running_window_is_not_judged_yet(): void {
        // „Jetzt" liegt im 2-Wochen-Fenster — der Ersatzruhetag kann noch kommen.
        $findings = $this->checker()->checkUser(1, $this->sundayWorkWindow(), CarbonImmutable::parse('2026-03-10'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SUBSTITUTE_REST_DAY));
    }

    public function test_holiday_work_uses_the_eight_week_window(): void {
        // Feiertag 01.05.2026 gearbeitet + alle Werktage bis 25.06. belegt (56-Tage-Fenster).
        $byDate = ['2026-05-01' => [$this->span('2026-05-01 08:00', '2026-05-01 12:00')]];
        for ($d = CarbonImmutable::parse('2026-05-02'); $d->lessThanOrEqualTo(CarbonImmutable::parse('2026-06-25')); $d = $d->addDay()) {
            if ($d->isSunday()) {
                continue;
            }
            $ds = $d->toDateString();
            $byDate[$ds] = [$this->span("$ds 08:00", "$ds 14:00")];
        }

        $findings = $this->checker()->checkUser(1, $byDate, CarbonImmutable::parse('2026-07-01'), ['2026-05-01']);

        $rest = $this->findingsOf($findings, AttendanceComplianceChecker::KIND_SUBSTITUTE_REST_DAY);
        $this->assertCount(1, $rest);
        $this->assertSame('2026-05-01', $rest[0]->date);
    }

    // ── MVP-696: ArbZG §11 Abs. 1 — 15 freie Sonntage ───────────────────

    /**
     * @return array<string, list<array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}>>
     */
    private function workedSundays2027(int $count): array {
        $byDate = [];
        $sunday = CarbonImmutable::parse('2027-01-03'); // erster Sonntag 2027
        for ($i = 0; $i < $count; $i++) {
            $ds = $sunday->addWeeks($i)->toDateString();
            $byDate[$ds] = [$this->span("$ds 09:00", "$ds 13:00")];
        }

        return $byDate;
    }

    public function test_more_than_37_worked_sundays_break_the_yearly_minimum(): void {
        // 2027 hat 52 Sonntage; 38 gearbeitet → maximal 14 freie < 15.
        $findings = $this->checker()->checkUser(1, $this->workedSundays2027(38), CarbonImmutable::parse('2028-01-15'));

        $free = $this->findingsOf($findings, AttendanceComplianceChecker::KIND_FREE_SUNDAYS);
        $this->assertCount(1, $free);
        $this->assertSame('2027-09-19', $free[0]->date); // letzter Arbeits-Sonntag
        $this->assertSame(14, $free[0]->value);
        $this->assertSame(15, $free[0]->threshold);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $free[0]->severity);
    }

    public function test_37_worked_sundays_still_reach_15_free_ones(): void {
        $findings = $this->checker()->checkUser(1, $this->workedSundays2027(37), CarbonImmutable::parse('2028-01-15'));

        $this->assertSame([], $this->findingsOf($findings, AttendanceComplianceChecker::KIND_FREE_SUNDAYS));
    }

    // ── Einheiten-Formatierung (Report/History/CSV) ─────────────────────

    public function test_value_units_follow_the_rule_kind(): void {
        $this->assertSame(AttendanceComplianceFinding::UNIT_DAYS, AttendanceComplianceFinding::unitFor(AttendanceComplianceChecker::KIND_LATE_RECORDING));
        $this->assertSame(AttendanceComplianceFinding::UNIT_COUNT, AttendanceComplianceFinding::unitFor(AttendanceComplianceChecker::KIND_FREE_SUNDAYS));
        $this->assertSame(AttendanceComplianceFinding::UNIT_COUNT, AttendanceComplianceFinding::unitFor(AttendanceComplianceChecker::KIND_SUBSTITUTE_REST_DAY));
        $this->assertSame(AttendanceComplianceFinding::UNIT_MINUTES, AttendanceComplianceFinding::unitFor(AttendanceComplianceChecker::KIND_NIGHT_WORK));
        $this->assertSame('14', AttendanceComplianceFinding::formatValue(AttendanceComplianceChecker::KIND_FREE_SUNDAYS, 14));
    }
}
