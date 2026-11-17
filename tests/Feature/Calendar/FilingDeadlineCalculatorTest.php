<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingDeadlineCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\Period\PeriodKind;
use App\Services\Calendar\Filing\{FilingDeadlineCalculator, FilingProfile};
use App\Services\Calendar\HolidayService;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Der eine Fristenrechner im Kalender (Feature 197, MVP-1631/1676) gegen die
 * eingefrorenen, von Hand geprüften Termine unter `tests/Fixtures/filing-deadlines`
 * (Quellen dort). Kundenprofile rechnen nur mit den gesetzlichen Feiertagen am
 * Ort des Finanzamts bzw. der Einzugsstelle.
 */
final class FilingDeadlineCalculatorTest extends TestCase {
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function taxCases(): iterable {
        foreach (require dirname(__DIR__, 2) . '/Fixtures/filing-deadlines/customer-tax-2025-2027.php' as $case => $expected) {
            yield $case => [$case, $expected];
        }
    }

    #[DataProvider('taxCases')]
    public function test_tax_deadlines_match_the_frozen_cases(string $case, string $expected): void {
        [$region, $flags, $kind, $period] = explode('|', $case);
        $flags = explode(',', $flags);
        $agriculture = in_array('agri7', $flags, true);
        $profile = new FilingProfile(
            vatInterval: PeriodKind::Month,
            vatExtension: in_array('ext', $flags, true),
            taxAdvised: in_array('adv', $flags, true),
            holidayRegion: $region,
            agriculture: $agriculture,
            fiscalYearStartMonth: $agriculture ? 7 : 1,
        );
        $calculator = app(FilingDeadlineCalculator::class);
        $key = PeriodKey::parse($period);

        $actual = match ($kind) {
            'vat_advance' => $calculator->vatAdvance($profile, $key),
            'vat_special_prepayment' => $calculator->specialPrepayment($profile, $key->year),
            'wage_tax' => $calculator->wageTaxAdvance($profile, $key),
            'recapitulative' => $calculator->recapitulative($profile, $key),
            'annual_return' => $calculator->annualReturn($profile, $key->year),
            default => $this->fail('Unbekannte Fristart im Fixture: ' . $kind),
        };

        $this->assertSame($expected, $actual->toDateString(), $case);
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function contributionCases(): iterable {
        foreach (require dirname(__DIR__, 2) . '/Fixtures/filing-deadlines/social-security-2026-2027.php' as $region => $months) {
            foreach ($months as $month => [$statement, $due]) {
                yield $region . '|' . $month => [$region, $month, $statement, $due];
            }
        }
    }

    #[DataProvider('contributionCases')]
    public function test_social_security_dates_match_the_published_tables(string $region, string $month, string $statement, string $due): void {
        $calculator = app(FilingDeadlineCalculator::class);
        $key = PeriodKey::parse($month);

        $this->assertSame($due, $calculator->contributionDue([$region], $key)->toDateString(), 'Fälligkeit ' . $month);
        $this->assertSame($statement, $calculator->contributionStatement([$region], $key)->toDateString(), 'Beitragsnachweis ' . $month);
    }

    /** Abnahme 8: ein Feiertag nur am Sitz einer zweiten Einzugsstelle zieht beide Termine vor. */
    public function test_the_earliest_date_of_several_collecting_agencies_applies(): void {
        $calculator = app(FilingDeadlineCalculator::class);
        $may = PeriodKey::of(PeriodKind::Month, 2027, 5);

        $this->assertSame('2027-05-27', $calculator->contributionDue(['Germany\\Berlin'], $may)->toDateString());
        $this->assertSame('2027-05-26', $calculator->contributionDue(['Germany\\Berlin', 'Germany\\Bavaria'], $may)->toDateString());
        $this->assertSame('2027-05-24', $calculator->contributionStatement(['Germany\\Berlin', 'Germany\\Bavaria'], $may)->toDateString());
    }

    /** Silvester und Pfingstsonntag führt Yasumi als „sonstige“ — gesetzlich sind sie es nicht. */
    public function test_statutory_holidays_leave_out_observances_but_keep_the_saarland_assumption(): void {
        $holidays = app(HolidayService::class);

        $this->assertFalse($holidays->isStatutoryHoliday(CarbonImmutable::create(2026, 12, 31) ?? CarbonImmutable::now(), 'Germany\\Berlin'));
        $this->assertTrue($holidays->isStatutoryHoliday(CarbonImmutable::create(2026, 10, 3) ?? CarbonImmutable::now(), 'Germany\\Berlin'));
        $this->assertTrue($holidays->isStatutoryHoliday(CarbonImmutable::create(2025, 8, 15) ?? CarbonImmutable::now(), 'Germany\\Saarland'));
        $this->assertFalse($holidays->isStatutoryHoliday(CarbonImmutable::create(2025, 8, 15) ?? CarbonImmutable::now(), 'Germany\\Bavaria'));
    }
}
