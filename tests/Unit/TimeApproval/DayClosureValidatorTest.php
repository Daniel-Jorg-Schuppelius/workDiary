<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosureValidatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\TimeApproval;

use App\Services\TimeApproval\DayClosureValidator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Alle 7 Checks aus docs/tagesabschluss.md §4 — je positiv (Befund) und
 * negativ (kein Befund). Der Validator ist pure: Stempel/Buchungen/Soll
 * und die Pausenregeln (ArbZG §4) werden injiziert, keine DB.
 */
class DayClosureValidatorTest extends TestCase {
    private DayClosureValidator $validator;

    private CarbonImmutable $now;

    protected function setUp(): void {
        parent::setUp();

        $this->validator = new DayClosureValidator([
            ['after_minutes' => 360, 'required_minutes' => 30],
            ['after_minutes' => 540, 'required_minutes' => 45],
        ]);
        $this->now = CarbonImmutable::parse('2026-06-10 18:00:00');
    }

    // ── 1. attendance.missing_close ⛔ ──────────────────────────────────

    public function test_open_attendance_yields_blocking_missing_close(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', null)],
            [],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_ATTENDANCE_MISSING_CLOSE);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_BLOCKING, $issue['severity']);
        $this->assertTrue($this->validator->hasBlocking($issues));
    }

    public function test_closed_attendance_has_no_missing_close(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [$this->entry(480)],
            480,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_ATTENDANCE_MISSING_CLOSE));
    }

    // ── 2. time.unallocated_minutes ⛔ ──────────────────────────────────

    public function test_unallocated_minutes_above_tolerance_is_blocking(): void {
        // Netto 480 (8:00–16:30 minus 30 Pause), nur 400 verbucht → 80 offen.
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [$this->entry(400)],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_TIME_UNALLOCATED);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_BLOCKING, $issue['severity']);
        $this->assertSame(80, $issue['meta']['minutes']);
    }

    public function test_unallocated_minutes_within_tolerance_passes(): void {
        // 480 Netto, 476 verbucht → 4 min Differenz ≤ 5 min Toleranz.
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [$this->entry(476)],
            480,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_TIME_UNALLOCATED));
    }

    public function test_uncounted_entries_do_not_reduce_unallocated_minutes(): void {
        // Pausen-/Abwesenheits-Buchungen (counted=false) zählen nicht als verbucht.
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [$this->entry(480, counted: false)],
            480,
            $this->now,
        );

        $this->assertNotNull($this->findIssue($issues, DayClosureValidator::CHECK_TIME_UNALLOCATED));
    }

    // ── 3. break.required ⛔ ────────────────────────────────────────────

    public function test_missing_statutory_break_is_blocking(): void {
        // Brutto 510 min (> 6 h) erfordert 30 min — nur 15 erfasst.
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 15)],
            [$this->entry(495)],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_BREAK_REQUIRED);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_BLOCKING, $issue['severity']);
        $this->assertSame(30, $issue['meta']['required']);
        $this->assertSame(15, $issue['meta']['taken']);
    }

    public function test_sufficient_break_passes_and_nine_hours_require_45(): void {
        // 30 min reichen bei < 9 h Brutto …
        $ok = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [$this->entry(480)],
            480,
            $this->now,
        );
        $this->assertNull($this->findIssue($ok, DayClosureValidator::CHECK_BREAK_REQUIRED));

        // … aber nicht bei > 9 h Brutto (dann 45 min Pflicht).
        $tooLong = $this->validator->validate(
            [$this->span('07:00', '17:00', 30)],
            [$this->entry(570)],
            480,
            $this->now,
        );
        $issue = $this->findIssue($tooLong, DayClosureValidator::CHECK_BREAK_REQUIRED);
        $this->assertNotNull($issue);
        $this->assertSame(45, $issue['meta']['required']);
    }

    // ── 4. time.gap_in_attendance ⚠ ────────────────────────────────────

    public function test_gap_over_threshold_without_break_marker_warns(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '12:00'), $this->span('12:30', '16:00')],
            [$this->entry(450)],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_GAP_IN_ATTENDANCE);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_WARNING, $issue['severity']);
        $this->assertSame(30, $issue['meta']['minutes']);
    }

    public function test_gap_covered_by_recorded_breaks_does_not_warn(): void {
        // Gleiche Lücke, aber 30 min Pause erfasst → Pausen-Marker deckt sie.
        $issues = $this->validator->validate(
            [$this->span('08:00', '12:00', 30), $this->span('12:30', '16:00')],
            [$this->entry(420)],
            480,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_GAP_IN_ATTENDANCE));
    }

    public function test_small_gap_below_threshold_does_not_warn(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '12:00'), $this->span('12:10', '16:00')],
            [$this->entry(470)],
            480,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_GAP_IN_ATTENDANCE));
    }

    // ── 5. balance.threshold ⚠ ─────────────────────────────────────────

    public function test_day_balance_beyond_two_hours_warns(): void {
        // Netto 630 (7:00–18:00 minus 30) bei Soll 480 → +150 min > 120.
        $issues = $this->validator->validate(
            [$this->span('07:00', '18:00', 30)],
            [$this->entry(630)],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_BALANCE_THRESHOLD);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_WARNING, $issue['severity']);
        $this->assertSame(150, $issue['meta']['balance']);
    }

    public function test_day_balance_within_two_hours_does_not_warn(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '17:00', 30)],
            [$this->entry(510)],
            480,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_BALANCE_THRESHOLD));
    }

    // ── 6. entry.missing_comment ⚠ ─────────────────────────────────────

    public function test_billable_entry_without_comment_warns(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [
                $this->entry(240, billable: true, hasComment: false),
                $this->entry(240, billable: true, hasComment: false),
            ],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_ENTRY_MISSING_COMMENT);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_WARNING, $issue['severity']);
        $this->assertSame(2, $issue['meta']['count']);
    }

    public function test_billable_with_comment_and_non_billable_without_pass(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [
                $this->entry(240, billable: true, hasComment: true),
                $this->entry(240, billable: false, hasComment: false),
            ],
            480,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_ENTRY_MISSING_COMMENT));
    }

    // ── 7. worktime.overrun ⚠ ──────────────────────────────────────────

    public function test_net_worktime_over_ten_hours_warns(): void {
        // 7:00–18:30 minus 45 Pause = 645 min Netto > 600.
        $issues = $this->validator->validate(
            [$this->span('07:00', '18:30', 45)],
            [$this->entry(645)],
            480,
            $this->now,
        );

        $issue = $this->findIssue($issues, DayClosureValidator::CHECK_WORKTIME_OVERRUN);
        $this->assertNotNull($issue);
        $this->assertSame(DayClosureValidator::SEVERITY_WARNING, $issue['severity']);
        $this->assertSame(645, $issue['meta']['minutes']);
    }

    public function test_net_worktime_at_ten_hours_does_not_warn(): void {
        // Exakt 600 min Netto (7:00–17:30 minus 30) → keine Überschreitung.
        $issues = $this->validator->validate(
            [$this->span('07:00', '17:30', 30)],
            [$this->entry(600)],
            600,
            $this->now,
        );

        $this->assertNull($this->findIssue($issues, DayClosureValidator::CHECK_WORKTIME_OVERRUN));
    }

    // ── Sortierung & Hilfsfunktionen ────────────────────────────────────

    public function test_issues_are_sorted_blocking_before_warning(): void {
        // Offener Stempel (⛔) + Saldo (⚠) + fehlender Kommentar (⚠).
        $issues = $this->validator->validate(
            [$this->span('07:00', null)],
            [$this->entry(700, billable: true, hasComment: false)],
            480,
            $this->now,
        );

        $this->assertNotEmpty($issues);
        $severities = array_column($issues, 'severity');
        $firstWarning = array_search(DayClosureValidator::SEVERITY_WARNING, $severities, true);
        $lastBlocking = max(array_keys($severities, DayClosureValidator::SEVERITY_BLOCKING, true));
        $this->assertTrue($firstWarning === false || $lastBlocking < $firstWarning);
    }

    public function test_fully_consistent_day_has_no_issues(): void {
        $issues = $this->validator->validate(
            [$this->span('08:00', '16:30', 30)],
            [$this->entry(480, billable: true, hasComment: true)],
            480,
            $this->now,
        );

        $this->assertSame([], $issues);
        $this->assertFalse($this->validator->hasBlocking($issues));
    }

    // ── intern ─────────────────────────────────────────────────────────

    /** @return array{started_at: CarbonImmutable, ended_at: ?CarbonImmutable, break_minutes: int} */
    private function span(string $start, ?string $end, int $breakMinutes = 0): array {
        return [
            'started_at' => CarbonImmutable::parse("2026-06-10 {$start}:00"),
            'ended_at' => $end !== null ? CarbonImmutable::parse("2026-06-10 {$end}:00") : null,
            'break_minutes' => $breakMinutes,
        ];
    }

    /** @return array{minutes:int, billable:bool, has_comment:bool, counted:bool} */
    private function entry(int $minutes, bool $billable = false, bool $hasComment = true, bool $counted = true): array {
        return [
            'minutes' => $minutes,
            'billable' => $billable,
            'has_comment' => $hasComment,
            'counted' => $counted,
        ];
    }

    /**
     * @param  list<array{code:string, severity:string, meta:array<string, int|string>}>  $issues
     * @return array{code:string, severity:string, meta:array<string, int|string>}|null
     */
    private function findIssue(array $issues, string $code): ?array {
        foreach ($issues as $issue) {
            if ($issue['code'] === $code) {
                return $issue;
            }
        }

        return null;
    }
}
