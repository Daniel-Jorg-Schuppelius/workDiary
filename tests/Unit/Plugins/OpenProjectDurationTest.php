<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectDurationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\OpenProject\Sources\OpenProjectDuration;
use PHPUnit\Framework\TestCase;

class OpenProjectDurationTest extends TestCase {
    public function test_to_minutes_parses_iso_durations(): void {
        $this->assertSame(150, OpenProjectDuration::toMinutes('PT2H30M'));
        $this->assertSame(480, OpenProjectDuration::toMinutes('PT8H'));
        $this->assertSame(45, OpenProjectDuration::toMinutes('PT45M'));
        $this->assertSame(1560, OpenProjectDuration::toMinutes('P1DT2H')); // 24h + 2h
        $this->assertSame(0, OpenProjectDuration::toMinutes(''));
        $this->assertSame(0, OpenProjectDuration::toMinutes('garbage'));
    }

    public function test_from_minutes_formats_iso_durations(): void {
        $this->assertSame('PT2H30M', OpenProjectDuration::fromMinutes(150));
        $this->assertSame('PT8H', OpenProjectDuration::fromMinutes(480));
        $this->assertSame('PT45M', OpenProjectDuration::fromMinutes(45));
        $this->assertSame('PT0H', OpenProjectDuration::fromMinutes(0));
    }

    public function test_round_trip_is_stable(): void {
        foreach ([15, 60, 90, 125, 480] as $minutes) {
            $this->assertSame($minutes, OpenProjectDuration::toMinutes(OpenProjectDuration::fromMinutes($minutes)));
        }
    }
}
