<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : YouthWorkProtectionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Models\Platform\Organization;
use App\Services\Compliance\{AttendanceComplianceChecker, AttendanceComplianceFinding};
use App\Services\Timekeeping\BreakRuleEvaluator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** MVP-1001: Jugendarbeitsschutz, Nachtzeit je Organisation, Feiertage im §3-Durchschnitt. */
final class YouthWorkProtectionTest extends TestCase {
    /** @param array<string, mixed> $overrides */
    private function checker(array $overrides = []): AttendanceComplianceChecker {
        return new AttendanceComplianceChecker(array_replace(Organization::COMPLIANCE_DEFAULTS, $overrides), new BreakRuleEvaluator);
    }

    /** @return array{started_at: CarbonImmutable, ended_at: CarbonImmutable, break_minutes: int} */
    private function span(string $start, string $end, int $break = 0): array {
        return ['started_at' => CarbonImmutable::parse($start), 'ended_at' => CarbonImmutable::parse($end), 'break_minutes' => $break];
    }

    /**
     * @param  list<AttendanceComplianceFinding>  $findings
     * @return list<string>
     */
    private function kinds(array $findings, string $date): array {
        $kinds = [];
        foreach ($findings as $finding) {
            if ($finding->date === $date && in_array($finding->kind, AttendanceComplianceChecker::YOUTH_KINDS, true)) {
                $kinds[] = $finding->kind;
            }
        }
        sort($kinds);

        return $kinds;
    }

    public function test_minors_are_checked_against_the_youth_work_protection_act(): void {
        $days = [
            // Mo: 9 h brutto, 30 min Pause → 8:30 netto (> 8 h, Pause < 60 min).
            '2026-06-08' => [$this->span('2026-06-08 07:00', '2026-06-08 16:00', 30)],
            // Di: Beginn 02:00 nach Ende 16:00 am Vortag → nur 10 h Freizeit, Nachtarbeit.
            '2026-06-09' => [$this->span('2026-06-09 02:00', '2026-06-09 06:30', 0)],
            '2026-06-10' => [$this->span('2026-06-10 08:00', '2026-06-10 12:00', 0)],
            '2026-06-11' => [$this->span('2026-06-11 08:00', '2026-06-11 12:00', 0)],
            '2026-06-12' => [$this->span('2026-06-12 08:00', '2026-06-12 12:00', 0)],
            // Sa: sechster Arbeitstag, Wochenende.
            '2026-06-13' => [$this->span('2026-06-13 08:00', '2026-06-13 12:00', 0)],
        ];
        $birthDate = CarbonImmutable::parse('2010-09-01'); // 15 Jahre

        $findings = $this->checker()->checkUser(7, $days, CarbonImmutable::parse('2026-06-20'), birthDate: $birthDate);

        $this->assertSame([AttendanceComplianceChecker::KIND_YOUTH_BREAK, AttendanceComplianceChecker::KIND_YOUTH_DAILY_HOURS], $this->kinds($findings, '2026-06-08'));
        $this->assertSame([AttendanceComplianceChecker::KIND_YOUTH_NIGHT, AttendanceComplianceChecker::KIND_YOUTH_REST], $this->kinds($findings, '2026-06-09'));
        $night = array_values(array_filter($findings, static fn ($f): bool => $f->kind === AttendanceComplianceChecker::KIND_YOUTH_NIGHT))[0];
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_ERROR, $night->severity, 'unter 16: Verstoß');
        $this->assertSame(240, $night->value, '02:00–06:00');
        $this->assertSame([AttendanceComplianceChecker::KIND_YOUTH_WEEKEND], $this->kinds($findings, '2026-06-13'));
        $this->assertSame([AttendanceComplianceChecker::KIND_YOUTH_FIVE_DAYS], $this->kinds($findings, '2026-06-14'));

        $seventeen = $this->checker()->checkUser(7, $days, CarbonImmutable::parse('2026-06-20'), birthDate: CarbonImmutable::parse('2009-01-01'));
        $night = array_values(array_filter($seventeen, static fn ($f): bool => $f->kind === AttendanceComplianceChecker::KIND_YOUTH_NIGHT))[0];
        $this->assertSame(AttendanceComplianceFinding::SEVERITY_WARNING, $night->severity, 'ab 16: Hinweis wegen Branchenausnahmen');

        $adult = $this->checker()->checkUser(7, $days, CarbonImmutable::parse('2026-06-20'), birthDate: CarbonImmutable::parse('2008-06-09'));
        $youthKinds = array_intersect(array_map(static fn ($f): string => $f->kind, $adult), AttendanceComplianceChecker::YOUTH_KINDS);
        $this->assertSame([AttendanceComplianceChecker::KIND_YOUTH_BREAK, AttendanceComplianceChecker::KIND_YOUTH_DAILY_HOURS], array_values(array_unique($youthKinds)),
            'ab dem 18. Geburtstag (09.06.) keine Jugendprüfung mehr');
        $this->assertSame([], array_intersect(array_map(static fn ($f): string => $f->kind, $this->checker()->checkUser(7, $days)), AttendanceComplianceChecker::YOUTH_KINDS));
    }

    public function test_night_window_follows_the_organisation_setting(): void {
        // 14:00–00:30 mit 60 min Pause = 9:30 netto; Nachtanteil 23–6 = 90 min (keine Nachtarbeit),
        // im Bäckerei-Fenster 22–5 = 150 min (> 2 h) → Nachtarbeit über 8 h.
        $late = ['2026-06-10' => [$this->span('2026-06-10 14:00', '2026-06-11 00:30', 60)]];
        $standard = collect($this->checker()->checkUser(1, $late))->firstWhere('kind', AttendanceComplianceChecker::KIND_NIGHT_WORK);
        $bakery = collect($this->checker(['night_start_hour' => 22, 'night_end_hour' => 5])->checkUser(1, $late))->firstWhere('kind', AttendanceComplianceChecker::KIND_NIGHT_WORK);

        $this->assertNull($standard);
        $this->assertNotNull($bakery);
    }

    public function test_holidays_do_not_count_as_workdays_in_the_six_month_average(): void {
        // 4 Wochen Mo–Fr je 9:36 h netto (576 min) = 48 h/Woche; Durchschnitt je Werktag (Mo–Sa) = 480 min.
        $days = [];
        for ($d = CarbonImmutable::parse('2026-06-01'); $d->lessThanOrEqualTo(CarbonImmutable::parse('2026-06-28')); $d = $d->addDay()) {
            if ($d->isWeekday()) {
                $days[$d->toDateString()] = [$this->span($d->toDateString() . ' 07:00', $d->toDateString() . ' 17:21', 45)];
            }
        }
        $kind = AttendanceComplianceChecker::KIND_SIX_MONTH_AVERAGE;
        $without = collect($this->checker()->checkUser(1, $days, CarbonImmutable::parse('2026-07-15')))->where('kind', $kind);
        $with = collect($this->checker()->checkUser(1, $days, CarbonImmutable::parse('2026-07-15'), ['2026-06-06', '2026-06-13']))->where('kind', $kind);

        $this->assertCount(0, $without, 'genau 8 h je Werktag');
        $this->assertCount(1, $with, 'zwei Feiertage weniger im Nenner heben den Durchschnitt über 8 h');
    }
}
