<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeMultiManningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Services\Compliance\{AttendanceComplianceFinding, DrivingTimeComplianceChecker};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** MVP-1014: Mehrfahrerbetrieb (Art. 8 Abs. 5), Beifahrerzeit und Fähre/Zug (Art. 9). */
final class DrivingTimeMultiManningTest extends TestCase {
    /** @return array{started_at: CarbonImmutable, ended_at: CarbonImmutable, role: string, ferry: bool, multi: bool} */
    private function trip(string $start, string $end, string $role = DrivingTimeComplianceChecker::ROLE_DRIVER, bool $multi = false, bool $ferry = false): array {
        return ['started_at' => CarbonImmutable::parse($start), 'ended_at' => CarbonImmutable::parse($end), 'role' => $role, 'ferry' => $ferry, 'multi' => $multi];
    }

    /**
     * @param  list<AttendanceComplianceFinding>  $findings
     * @return list<AttendanceComplianceFinding>
     */
    private function rest(array $findings): array {
        return array_values(array_filter($findings, static fn (AttendanceComplianceFinding $f): bool => $f->kind === DrivingTimeComplianceChecker::KIND_DAILY_REST));
    }

    /** Vier Tage mit je 9,5 h Ruhe: allein ab der vierten Reduzierung ein Verstoß, im Mehrfahrerbetrieb regelkonform. */
    private function fourReducedRests(bool $multi): array {
        $trips = [];
        foreach (['2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04', '2026-06-05'] as $date) {
            $trips[] = $this->trip("$date 05:00", "$date 09:30", multi: $multi);
            $trips[] = $this->trip("$date 10:15", "$date 14:30", multi: $multi);
            $trips[] = $this->trip("$date 15:00", "$date 19:30", role: $multi ? DrivingTimeComplianceChecker::ROLE_CO_DRIVER : DrivingTimeComplianceChecker::ROLE_DRIVER, multi: $multi);
        }

        return $trips;
    }

    public function test_multi_manning_accepts_nine_hours_of_rest_without_counting_reductions(): void {
        $checker = new DrivingTimeComplianceChecker;
        $alone = $this->rest($checker->checkUser(1, $this->fourReducedRests(false)));
        $this->assertCount(1, $alone);
        $this->assertSame(660, $alone[0]->threshold);

        $this->assertSame([], $this->rest($checker->checkUser(1, $this->fourReducedRests(true))));
    }

    public function test_co_driver_time_is_not_driving_but_interrupts_the_rest(): void {
        $checker = new DrivingTimeComplianceChecker;
        $trips = [
            $this->trip('2026-06-01 06:00', '2026-06-01 10:00', multi: true),
            $this->trip('2026-06-01 20:00', '2026-06-02 02:00', role: DrivingTimeComplianceChecker::ROLE_CO_DRIVER, multi: true),
            $this->trip('2026-06-02 09:00', '2026-06-02 12:00', multi: true),
        ];
        $findings = $checker->checkUser(1, $trips);
        $this->assertSame([], array_filter($findings, static fn (AttendanceComplianceFinding $f): bool => $f->kind === DrivingTimeComplianceChecker::KIND_DAILY_DRIVING));
        $rest = $this->rest($findings);
        $this->assertCount(1, $rest);
        $this->assertSame(420, $rest[0]->value);
        $this->assertSame(540, $rest[0]->threshold);

        $onlyCoDriving = [$this->trip('2026-06-01 06:00', '2026-06-01 17:00', role: DrivingTimeComplianceChecker::ROLE_CO_DRIVER, multi: true)];
        $this->assertSame([], $checker->checkUser(1, $onlyCoDriving));
    }

    public function test_a_ferry_crossing_counts_as_rest(): void {
        $checker = new DrivingTimeComplianceChecker;
        $day = static fn (string $d): array => [['2026-06-0' . $d . ' 07:00', '2026-06-0' . $d . ' 11:00'], ['2026-06-0' . $d . ' 11:45', '2026-06-0' . $d . ' 16:00']];
        $trips = [];
        foreach ($day('1') as [$s, $e]) {
            $trips[] = $this->trip($s, $e);
        }
        $trips[] = $this->trip('2026-06-01 21:00', '2026-06-02 05:00', ferry: true);
        foreach ($day('2') as [$s, $e]) {
            $trips[] = $this->trip($s, $e);
        }
        $this->assertSame([], $this->rest($checker->checkUser(1, $trips)));

        $trips[2]['ferry'] = false;
        $this->assertNotSame([], $this->rest($checker->checkUser(1, $trips)));
    }
}
