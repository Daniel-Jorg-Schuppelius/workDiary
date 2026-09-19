<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperatingWindowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Scheduling;

use App\Scheduling\{Cadence, CadenceType, OperatingWindow};
use PHPUnit\Framework\TestCase;

/**
 * Produktionsmeldung 2026-09-19: Auf web141 (Betrieb 08–24 Uhr) standen alle
 * Nacht-Defaults (00:10–05:45) jeden Morgen als überfällig — der Server war
 * zur Soll-Zeit aus. Das Betriebsfenster rückt sie an den Fensterbeginn.
 */
class OperatingWindowTest extends TestCase {
    private function nightOff(): OperatingWindow {
        $window = OperatingWindow::fromTimes('08:00', '00:00');
        $this->assertNotNull($window);

        return $window;
    }

    private function daily(string $time): Cadence {
        return new Cadence(CadenceType::DailyAt, $time);
    }

    public function test_without_a_proper_window_the_server_runs_around_the_clock(): void {
        $this->assertNull(OperatingWindow::fromTimes(null, null));
        $this->assertNull(OperatingWindow::fromTimes('08:00', null));
        $this->assertNull(OperatingWindow::fromTimes('08:00', '08:00'));
        $this->assertNull(OperatingWindow::fromTimes('8 Uhr', '00:00'));
        $this->assertNull(OperatingWindow::fromTimes('24:00', '08:00'));
    }

    public function test_label_shows_midnight_as_end_of_day(): void {
        $this->assertSame('08:00–24:00', $this->nightOff()->label());
        $this->assertSame('06:00–22:00', OperatingWindow::fromTimes('06:00', '22:00')?->label());
    }

    public function test_times_inside_the_window_stay_untouched(): void {
        $window = $this->nightOff();

        foreach (['08:00', '12:30', '21:50', '23:59'] as $time) {
            $cadence = $this->daily($time);
            $this->assertSame($cadence, $window->apply($cadence), $time);
        }
    }

    /** Die gemeldeten Jobs: nie früher als geplant, gestaucht in die ersten zwei Betriebsstunden. */
    public function test_night_times_move_to_the_start_of_the_window(): void {
        $window = $this->nightOff();

        $this->assertSame('08:00', $window->apply($this->daily('00:00'))->time);
        $this->assertSame('08:02', $window->apply($this->daily('00:10'))->time);
        $this->assertSame('08:32', $window->apply($this->daily('02:10'))->time);
        $this->assertSame('08:56', $window->apply($this->daily('03:45'))->time);
        $this->assertSame('09:26', $window->apply($this->daily('05:45'))->time);
        $this->assertSame('09:59', $window->apply($this->daily('07:59'))->time);
    }

    public function test_shifted_times_keep_their_order(): void {
        $window = $this->nightOff();
        $previous = -1;

        for ($minute = 0; $minute < 480; $minute++) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            [$hour, $min] = array_map('intval', explode(':', (string) $window->apply($this->daily($time))->time));
            $shifted = $hour * 60 + $min;

            $this->assertGreaterThanOrEqual(480, $shifted, $time);
            $this->assertGreaterThanOrEqual($previous, $shifted, $time);
            $previous = $shifted;
        }
    }

    public function test_frequent_cadences_are_not_shifted(): void {
        $window = $this->nightOff();

        foreach ([CadenceType::EveryMinute, CadenceType::EveryFiveMinutes, CadenceType::Hourly] as $type) {
            $cadence = new Cadence($type);
            $this->assertSame($cadence, $window->apply($cadence));
        }
    }

    public function test_weekly_and_monthly_days_follow_a_shift_past_midnight(): void {
        $window = OperatingWindow::fromTimes('06:00', '22:00');
        $this->assertNotNull($window);

        $weekly = $window->apply(new Cadence(CadenceType::WeeklyOn, '23:00', 6));
        $this->assertSame(['06:15', 0], [$weekly->time, $weekly->day]);

        $monthly = $window->apply(new Cadence(CadenceType::MonthlyOn, '23:00', 31));
        $this->assertSame(['06:15', 1], [$monthly->time, $monthly->day]);

        // Vor Mitternacht geschlossen, nach Mitternacht verschoben: gleicher Tag.
        $early = $window->apply(new Cadence(CadenceType::WeeklyOn, '02:00', 3));
        $this->assertSame(['07:00', 3], [$early->time, $early->day]);
    }

    public function test_cron_expressions_with_fixed_time_are_shifted(): void {
        $window = $this->nightOff();

        $this->assertSame('21 9 1 * *', $window->apply(new Cadence(CadenceType::Cron, expression: '25 5 1 * *'))->expression);
        $this->assertSame('15 20 2 * *', $window->apply(new Cadence(CadenceType::Cron, expression: '15 20 2 * *'))->expression);

        $pastMidnight = OperatingWindow::fromTimes('06:00', '22:00');
        $this->assertNotNull($pastMidnight);
        $this->assertSame('22 6 * * 2', $pastMidnight->apply(new Cadence(CadenceType::Cron, expression: '30 23 * * 1'))->expression);
    }

    public function test_cron_expressions_that_cannot_be_shifted_safely_stay_untouched(): void {
        $pastMidnight = OperatingWindow::fromTimes('06:00', '22:00');
        $this->assertNotNull($pastMidnight);

        foreach (['*/10 2 * * *', '0 1-3 * * *', '30 23 * * 1,3', '30 23 31 12 *'] as $expression) {
            $cadence = new Cadence(CadenceType::Cron, expression: $expression);
            $this->assertSame($cadence, $pastMidnight->apply($cadence), $expression);
        }
    }
}
