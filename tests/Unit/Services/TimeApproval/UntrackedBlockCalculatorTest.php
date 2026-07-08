<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UntrackedBlockCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Services\TimeApproval;

use App\Models\{Attendance, TimeEntry};
use App\Services\TimeApproval\UntrackedBlockCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Rang 37: Ableitung offener Zeitblöcke (Anwesenheit minus verortete Buchungen).
 */
class UntrackedBlockCalculatorTest extends TestCase {
    private function calc(): UntrackedBlockCalculator {
        return new UntrackedBlockCalculator;
    }

    private function attendance(string $from, ?string $to): Attendance {
        return (new Attendance)->forceFill(['started_at' => $from, 'ended_at' => $to]);
    }

    private function entry(?string $from, ?string $to): TimeEntry {
        return (new TimeEntry)->forceFill(['started_at' => $from, 'ended_at' => $to]);
    }

    private function now(): CarbonImmutable {
        return CarbonImmutable::parse('2026-07-07 23:00:00');
    }

    public function test_full_span_is_open_when_no_entries(): void {
        $blocks = $this->calc()->blocks(
            [$this->attendance('2026-07-07 08:00:00', '2026-07-07 12:00:00')],
            [],
            $this->now(),
        );

        $this->assertCount(1, $blocks);
        $this->assertSame(240, $blocks[0]['minutes']);
    }

    public function test_ranged_entry_carves_out_gaps(): void {
        $blocks = $this->calc()->blocks(
            [$this->attendance('2026-07-07 08:00:00', '2026-07-07 12:00:00')],
            [$this->entry('2026-07-07 09:00:00', '2026-07-07 10:00:00')],
            $this->now(),
        );

        $this->assertCount(2, $blocks);
        $this->assertSame(60, $blocks[0]['minutes']);   // 08:00–09:00
        $this->assertSame(120, $blocks[1]['minutes']);  // 10:00–12:00
    }

    public function test_open_attendance_counts_until_now(): void {
        $now = CarbonImmutable::parse('2026-07-07 10:00:00');
        $blocks = $this->calc()->blocks(
            [$this->attendance('2026-07-07 08:00:00', null)],
            [],
            $now,
        );

        $this->assertCount(1, $blocks);
        $this->assertSame(120, $blocks[0]['minutes']);
    }

    public function test_duration_only_entries_are_ignored_for_geometry(): void {
        $blocks = $this->calc()->blocks(
            [$this->attendance('2026-07-07 08:00:00', '2026-07-07 12:00:00')],
            [$this->entry(null, null)],
            $this->now(),
        );

        $this->assertCount(1, $blocks);
        $this->assertSame(240, $blocks[0]['minutes']);
    }

    public function test_gaps_below_threshold_are_dropped(): void {
        $blocks = $this->calc()->blocks(
            [$this->attendance('2026-07-07 08:00:00', '2026-07-07 12:00:00')],
            [$this->entry('2026-07-07 08:00:00', '2026-07-07 11:58:00')],
            $this->now(),
        );

        // Restlücke 11:58–12:00 = 2 min < MIN_BLOCK_MINUTES → verworfen.
        $this->assertSame([], $blocks);
    }
}
