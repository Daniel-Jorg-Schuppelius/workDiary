<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceComplianceCheckerTest.php
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
 * ArbZG-Schwellen werden gegen die Ist-Arbeitszeit geprüft. Die Schwellen
 * sind identisch zu den ScheduledShift-Regeln (Organization::COMPLIANCE_DEFAULTS:
 * 10 h/Tag, 11 h Ruhezeit, 48 h/Woche) und zum DayClosureValidator
 * (BreakRuleEvaluator: 30 min ab 6 h, 45 min ab 9 h).
 */
class AttendanceComplianceCheckerTest extends TestCase {
    private function checker(array $overrides = []): AttendanceComplianceChecker {
        $settings = array_replace(Organization::COMPLIANCE_DEFAULTS, $overrides);

        return new AttendanceComplianceChecker($settings, new BreakRuleEvaluator);
    }

    /**
     * @return array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int}
     */
    private function span(string $start, ?string $end, int $break = 0): array {
        return [
            'started_at' => CarbonImmutable::parse($start),
            'ended_at' => $end === null ? null : CarbonImmutable::parse($end),
            'break_minutes' => $break,
        ];
    }

    /**
     * @param  list<AttendanceComplianceFinding>  $findings
     */
    private function findingOf(array $findings, string $kind): ?AttendanceComplianceFinding {
        foreach ($findings as $f) {
            if ($f->kind === $kind) {
                return $f;
            }
        }

        return null;
    }

    public function test_more_than_ten_net_hours_per_day_is_a_violation(): void {
        // 06:00–17:00 = 11 h brutto − 30 min Pause = 10 h 30 min Netto > 10 h.
        $findings = $this->checker()->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 06:00', '2026-06-10 17:00', 30)],
        ]);

        $f = $this->findingOf($findings, AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS);
        $this->assertNotNull($f);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $f->severity);
        $this->assertSame(630, $f->value);   // 10:30 h netto
        $this->assertSame(600, $f->threshold); // 10 h
    }

    public function test_exactly_ten_net_hours_is_not_a_violation(): void {
        // 06:00–16:30 = 10:30 brutto − 30 min = 10:00 netto (== Grenze).
        $findings = $this->checker()->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 06:00', '2026-06-10 16:30', 30)],
        ]);

        $this->assertNull($this->findingOf($findings, AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS));
    }

    public function test_rest_period_below_eleven_hours_is_a_violation(): void {
        // Tag 1 endet 20:00, Tag 2 beginnt 06:00 → 10 h Ruhezeit < 11 h.
        $findings = $this->checker()->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 08:00', '2026-06-10 20:00', 60)],
            '2026-06-11' => [$this->span('2026-06-11 06:00', '2026-06-11 12:00', 0)],
        ]);

        $f = $this->findingOf($findings, AttendanceComplianceChecker::KIND_REST_PERIOD);
        $this->assertNotNull($f);
        $this->assertSame('2026-06-11', $f->date);
        $this->assertSame(600, $f->value);    // 10 h Gap
        $this->assertSame(660, $f->threshold); // 11 h
    }

    public function test_rest_period_of_twelve_hours_is_fine(): void {
        $findings = $this->checker()->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 08:00', '2026-06-10 18:00', 60)],
            '2026-06-11' => [$this->span('2026-06-11 06:00', '2026-06-11 12:00', 0)],
        ]);

        $this->assertNull($this->findingOf($findings, AttendanceComplianceChecker::KIND_REST_PERIOD));
    }

    public function test_missing_mandatory_break_is_a_violation(): void {
        // 7 h brutto ⇒ Pflichtpause 30 min; nur 10 min erfasst.
        $findings = $this->checker()->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 08:00', '2026-06-10 15:00', 10)],
        ]);

        $f = $this->findingOf($findings, AttendanceComplianceChecker::KIND_BREAK_MISSING);
        $this->assertNotNull($f);
        $this->assertSame(10, $f->value);     // erfasste Pause
        $this->assertSame(30, $f->threshold); // ArbZG §4 ab 6 h
    }

    public function test_sufficient_break_is_not_a_violation(): void {
        $findings = $this->checker()->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 08:00', '2026-06-10 15:00', 30)],
        ]);

        $this->assertNull($this->findingOf($findings, AttendanceComplianceChecker::KIND_BREAK_MISSING));
    }

    public function test_weekly_hours_above_average_limit_is_a_notice(): void {
        // 6 Tage × 9 h Netto = 54 h > 48 h. Jeder Tag 09:00–18:30 (−30 Pause).
        $byDate = [];
        foreach (['2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11', '2026-06-12', '2026-06-13'] as $d) {
            $byDate[$d] = [$this->span("$d 09:00", "$d 18:30", 30)];
        }
        $findings = $this->checker()->checkUser(1, $byDate);

        $f = $this->findingOf($findings, AttendanceComplianceChecker::KIND_MAX_WEEKLY_HOURS);
        $this->assertNotNull($f);
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_WARNING, $f->severity);
        $this->assertSame(2880, $f->threshold); // 48 h
        $this->assertGreaterThan(2880, $f->value);
    }

    public function test_compliance_off_yields_no_findings(): void {
        $findings = $this->checker(['mode' => Organization::COMPLIANCE_OFF])->checkUser(1, [
            '2026-06-10' => [$this->span('2026-06-10 06:00', '2026-06-10 18:00', 0)],
        ]);

        $this->assertSame([], $findings);
    }
}
