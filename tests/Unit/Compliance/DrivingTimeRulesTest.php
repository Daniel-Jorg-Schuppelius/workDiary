<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeRulesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Compliance;

use App\Services\Compliance\{AttendanceComplianceFinding, DrivingTimeComplianceChecker, DrivingTimeRules};
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Feature 144 (MVP-719): Grenzfälle der Lenk-/Ruhezeitregeln (VO (EG)
 * 561/2006 Art. 6–8) auf dem reinen Regelwerk und dem Checker — 2026-06-01
 * ist ein Montag, Wochen sind ISO-Wochen.
 */
class DrivingTimeRulesTest extends TestCase {
    /** @return array{started_at: CarbonImmutable, ended_at: CarbonImmutable} */
    private function trip(string $start, string $end): array {
        return ['started_at' => CarbonImmutable::parse($start), 'ended_at' => CarbonImmutable::parse($end)];
    }

    /**
     * @param  list<AttendanceComplianceFinding>  $findings
     * @return list<AttendanceComplianceFinding>
     */
    private function of(array $findings, string $kind): array {
        return array_values(array_filter($findings, static fn(AttendanceComplianceFinding $f): bool => $f->kind === $kind));
    }

    /**
     * Standard-Fahrtag mit gültiger Unterbrechung: 06:00–10:30 (4,5 h), 45 min
     * Pause, 11:15 + $secondMinutes.
     *
     * @return list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>
     */
    private function day(string $date, int $secondMinutes = 270): array {
        return [
            $this->trip("$date 06:00", "$date 10:30"),
            $this->trip("$date 11:15", CarbonImmutable::parse("$date 11:15")->addMinutes($secondMinutes)->format('Y-m-d H:i')),
        ];
    }

    // ── Regelwerk (rein) ─────────────────────────────────────────────────

    public function test_daily_limit_drops_to_nine_hours_after_two_extensions(): void {
        $this->assertSame(600, DrivingTimeRules::dailyLimitMinutes(0));
        $this->assertSame(600, DrivingTimeRules::dailyLimitMinutes(1));
        $this->assertSame(540, DrivingTimeRules::dailyLimitMinutes(2));
        $this->assertFalse(DrivingTimeRules::isExtendedDay(540));
        $this->assertTrue(DrivingTimeRules::isExtendedDay(541));
    }

    public function test_rest_classification_boundaries(): void {
        $this->assertSame(DrivingTimeRules::REST_REGULAR, DrivingTimeRules::classifyDailyRest(660));
        $this->assertSame(DrivingTimeRules::REST_REDUCED, DrivingTimeRules::classifyDailyRest(659));
        $this->assertSame(DrivingTimeRules::REST_REDUCED, DrivingTimeRules::classifyDailyRest(540));
        $this->assertSame(DrivingTimeRules::REST_INSUFFICIENT, DrivingTimeRules::classifyDailyRest(539));
        $this->assertSame(DrivingTimeRules::REST_REGULAR, DrivingTimeRules::classifyWeeklyRest(2700));
        $this->assertSame(DrivingTimeRules::REST_REDUCED, DrivingTimeRules::classifyWeeklyRest(1440));
        $this->assertSame(DrivingTimeRules::REST_INSUFFICIENT, DrivingTimeRules::classifyWeeklyRest(1439));
    }

    public function test_split_break_fifteen_plus_thirty_resets_driving_time(): void {
        // 2 h, 15 min Pause, 2 h, 30 min Pause, 2 h 15 → nach der zweiten Teilpause zählt neu.
        $result = DrivingTimeRules::evaluateBreaks([
            $this->trip('2026-06-01 06:00', '2026-06-01 08:00'),
            $this->trip('2026-06-01 08:15', '2026-06-01 10:15'),
            $this->trip('2026-06-01 10:45', '2026-06-01 13:00'),
        ]);

        $this->assertSame([], $result['violations']);
        $this->assertSame(135, $result['accumulated']);
        $this->assertSame(240, $result['max_accumulated']);
    }

    public function test_thirty_minutes_alone_does_not_replace_the_break(): void {
        // 2 h, 10 min (zählt nicht), 2 h, 30 min (nur erster Teil), 2 h 20 → 6 h 20 ohne gültige Unterbrechung.
        $result = DrivingTimeRules::evaluateBreaks([
            $this->trip('2026-06-01 06:00', '2026-06-01 08:00'),
            $this->trip('2026-06-01 08:10', '2026-06-01 10:10'),
            $this->trip('2026-06-01 10:40', '2026-06-01 13:00'),
        ]);

        $this->assertCount(1, $result['violations']);
        $this->assertSame('2026-06-01', $result['violations'][0]['date']);
        $this->assertSame(380, $result['violations'][0]['value']);
        $this->assertTrue($result['partial_break']);
    }

    // ── Tageslenkzeit (Art. 6 Abs. 1) ────────────────────────────────────

    public function test_exactly_nine_hours_with_break_is_compliant(): void {
        $findings = (new DrivingTimeComplianceChecker)->checkUser(1, $this->day('2026-06-01'));

        $this->assertSame([], $findings);
    }

    public function test_more_than_ten_hours_is_a_violation(): void {
        $trips = $this->day('2026-06-01');
        $trips[] = $this->trip('2026-06-01 16:30', '2026-06-01 17:31'); // 601 min gesamt

        $f = $this->of((new DrivingTimeComplianceChecker)->checkUser(1, $trips), DrivingTimeComplianceChecker::KIND_DAILY_DRIVING);

        $this->assertCount(1, $f);
        $this->assertSame(601, $f[0]->value);
        $this->assertSame(600, $f[0]->threshold);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $f[0]->severity);
    }

    public function test_third_extended_day_in_a_week_is_a_violation(): void {
        // Mo/Di/Mi je 9 h 30 (2 × 10-h-Verlängerung erlaubt, die dritte nicht).
        $trips = array_merge($this->day('2026-06-01', 300), $this->day('2026-06-02', 300), $this->day('2026-06-03', 300));

        $f = $this->of((new DrivingTimeComplianceChecker)->checkUser(1, $trips), DrivingTimeComplianceChecker::KIND_DAILY_DRIVING);

        $this->assertCount(1, $f);
        $this->assertSame('2026-06-03', $f[0]->date);
        $this->assertSame(570, $f[0]->value);
        $this->assertSame(540, $f[0]->threshold);
    }

    // ── Fahrtunterbrechung (Art. 7) ──────────────────────────────────────

    public function test_continuous_driving_over_four_and_a_half_hours_is_a_violation(): void {
        $f = $this->of(
            (new DrivingTimeComplianceChecker)->checkUser(1, [$this->trip('2026-06-01 06:00', '2026-06-01 10:31')]),
            DrivingTimeComplianceChecker::KIND_BREAK_MISSING,
        );

        $this->assertCount(1, $f);
        $this->assertSame(271, $f[0]->value);
        $this->assertSame(270, $f[0]->threshold);
    }

    // ── Wochen-/Doppelwochenlenkzeit (Art. 6 Abs. 2/3) ───────────────────

    public function test_weekly_driving_over_56_hours_is_a_violation(): void {
        // 7 × 8 h 10 = 57 h 10 (kein Tag über 9 h → keine Tagesbefunde).
        $trips = [];
        for ($d = 1; $d <= 7; $d++) {
            $trips = array_merge($trips, $this->day(sprintf('2026-06-%02d', $d), 220));
        }

        $findings = (new DrivingTimeComplianceChecker)->checkUser(1, $trips);
        $weekly = $this->of($findings, DrivingTimeComplianceChecker::KIND_WEEKLY_DRIVING);

        $this->assertSame([], $this->of($findings, DrivingTimeComplianceChecker::KIND_DAILY_DRIVING));
        $this->assertCount(1, $weekly);
        $this->assertSame('2026-06-07', $weekly[0]->date);
        $this->assertSame(3430, $weekly[0]->value);
        $this->assertSame(3360, $weekly[0]->threshold);
    }

    public function test_fortnight_over_90_hours_is_a_violation_even_if_each_week_is_compliant(): void {
        // 14 × 7 h = 49 h je Woche (≤ 56 h), 98 h in der Doppelwoche (> 90 h).
        $trips = [];
        for ($d = 1; $d <= 14; $d++) {
            $trips = array_merge($trips, $this->day(sprintf('2026-06-%02d', $d), 150));
        }

        $findings = (new DrivingTimeComplianceChecker)->checkUser(1, $trips);

        $this->assertSame([], $this->of($findings, DrivingTimeComplianceChecker::KIND_WEEKLY_DRIVING));
        $fortnight = $this->of($findings, DrivingTimeComplianceChecker::KIND_FORTNIGHT_DRIVING);
        $this->assertCount(1, $fortnight);
        $this->assertSame('2026-06-14', $fortnight[0]->date);
        $this->assertSame(5880, $fortnight[0]->value);
        $this->assertSame(5400, $fortnight[0]->threshold);
    }

    // ── Tägliche Ruhezeit (Art. 8 Abs. 2/4) ──────────────────────────────

    public function test_daily_rest_below_nine_hours_is_a_violation(): void {
        $f = $this->of((new DrivingTimeComplianceChecker)->checkUser(1, [
            $this->trip('2026-06-01 18:00', '2026-06-01 22:00'),
            $this->trip('2026-06-02 06:30', '2026-06-02 08:00'), // 8 h 30 Ruhezeit
        ]), DrivingTimeComplianceChecker::KIND_DAILY_REST);

        $this->assertCount(1, $f);
        $this->assertSame('2026-06-02', $f[0]->date);
        $this->assertSame(510, $f[0]->value);
        $this->assertSame(540, $f[0]->threshold);
    }

    public function test_fourth_reduced_daily_rest_in_a_week_is_a_violation(): void {
        // Mo–Fr: Fahrtende 21:00, Beginn 06:30 → 9 h 30 (reduziert); die vierte Reduzierung (Fr) ist unzulässig.
        $trips = [];
        for ($d = 1; $d <= 5; $d++) {
            $date = sprintf('2026-06-%02d', $d);
            $trips[] = $this->trip("$date 06:30", "$date 10:30");
            $trips[] = $this->trip("$date 20:00", "$date 21:00");
        }

        $f = $this->of((new DrivingTimeComplianceChecker)->checkUser(1, $trips), DrivingTimeComplianceChecker::KIND_DAILY_REST);

        $this->assertCount(1, $f);
        $this->assertSame('2026-06-05', $f[0]->date);
        $this->assertSame(570, $f[0]->value);
        $this->assertSame(660, $f[0]->threshold);
    }

    // ── Wöchentliche Ruhezeit (Art. 8 Abs. 6) ────────────────────────────

    public function test_weekly_rest_below_24_hours_is_a_violation_only_for_bounded_weeks(): void {
        // Drei Wochen täglich 06–08 und 18–20 Uhr: längste Pause 10 h. Nur die
        // mittlere Woche ist beidseitig durch Fahrten begrenzt.
        $trips = [];
        for ($d = 25; $d <= 31; $d++) {
            $trips[] = $this->trip(sprintf('2026-05-%02d 06:00', $d), sprintf('2026-05-%02d 08:00', $d));
            $trips[] = $this->trip(sprintf('2026-05-%02d 18:00', $d), sprintf('2026-05-%02d 20:00', $d));
        }
        for ($d = 1; $d <= 14; $d++) {
            $trips[] = $this->trip(sprintf('2026-06-%02d 06:00', $d), sprintf('2026-06-%02d 08:00', $d));
            $trips[] = $this->trip(sprintf('2026-06-%02d 18:00', $d), sprintf('2026-06-%02d 20:00', $d));
        }

        $f = $this->of((new DrivingTimeComplianceChecker)->checkUser(1, $trips), DrivingTimeComplianceChecker::KIND_WEEKLY_REST);

        $this->assertCount(1, $f);
        $this->assertSame('2026-06-07', $f[0]->date);
        $this->assertSame(600, $f[0]->value);
        $this->assertSame(1440, $f[0]->threshold);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $f[0]->severity);
    }

    public function test_reduced_weekly_rest_is_a_warning_and_twice_in_a_row_a_violation(): void {
        // Vier Wochen: Mo–Sa 06–08 Uhr, So 14–15 Uhr → längste Pause Sa 08:00 – So 14:00 = 30 h (reduziert).
        $trips = [];
        $cursor = CarbonImmutable::parse('2026-05-25');
        for ($i = 0; $i < 28; $i++) {
            $day = $cursor->addDays($i);
            $trips[] = $day->isSunday()
                ? $this->trip($day->format('Y-m-d 14:00'), $day->format('Y-m-d 15:00'))
                : $this->trip($day->format('Y-m-d 06:00'), $day->format('Y-m-d 08:00'));
        }

        $f = $this->of((new DrivingTimeComplianceChecker)->checkUser(1, $trips), DrivingTimeComplianceChecker::KIND_WEEKLY_REST);

        $this->assertCount(2, $f);
        $this->assertSame('2026-06-07', $f[0]->date);
        $this->assertSame(1800, $f[0]->value);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_WARNING, $f[0]->severity);
        $this->assertSame('2026-06-14', $f[1]->date);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $f[1]->severity);
        $this->assertSame(2700, $f[1]->threshold);
    }
}
