<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DepreciationCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Enums\Finance\{DepreciationMethod, FixedAssetStatus};
use App\Models\Accounting\FixedAsset;
use App\Services\Accounting\DepreciationCalculator;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use Tests\TestCase;

/**
 * Lineare AfA (Feature 133, MVP-698): monatsgenau im Anschaffungs- und
 * Abgangsjahr, Rundung je Zeile, Restdifferenz im letzten Jahr.
 */
class DepreciationCalculatorTest extends TestCase {
    private function asset(string $acquiredOn, string $cost, int $months, string $residual = '0.00', ?string $disposedOn = null): FixedAsset {
        $asset = new FixedAsset;
        $asset->forceFill([
            'name' => 'Laptop',
            'acquired_on' => $acquiredOn,
            'currency' => 'EUR',
            'acquisition_cost' => $cost,
            'residual_value' => $residual,
            'useful_life_months' => $months,
            'depreciation_method' => DepreciationMethod::Linear,
            'status' => $disposedOn === null ? FixedAssetStatus::Active : FixedAssetStatus::Disposed,
            'disposed_on' => $disposedOn,
        ]);

        return $asset;
    }

    /** @param list<\App\Services\Accounting\DepreciationScheduleRow> $rows */
    private function sum(array $rows): string {
        return NumberHelper::sumPrecise(array_map(fn ($row): string => $row->amount->getAmount(), $rows), 2, RoundingMode::HalfUp);
    }

    /** Kauf am 1. Oktober, 36 Monate: 3/12 im ersten Jahr (§ 7 Abs. 1 S. 4 EStG). */
    public function test_first_year_is_pro_rata_by_month(): void {
        $rows = (new DepreciationCalculator)->scheduleFor($this->asset('2026-10-01', '3600.00', 36));

        $this->assertCount(4, $rows);
        $this->assertSame([2026, 2027, 2028, 2029], array_map(fn ($row): int => $row->fiscalYear, $rows));
        $this->assertSame([3, 12, 12, 9], array_map(fn ($row): int => $row->months, $rows));
        $this->assertSame(['300.00', '1200.00', '1200.00', '900.00'], array_map(fn ($row): string => $row->amount->getAmount(), $rows));
        $this->assertSame('0.00', $rows[3]->bookValueEnd->getAmount());
        $this->assertSame('3600.00', $this->sum($rows));
    }

    public function test_the_residual_value_stays_on_the_books(): void {
        $rows = (new DepreciationCalculator)->scheduleFor($this->asset('2026-01-01', '1000.00', 12, '100.00'));

        $this->assertCount(1, $rows);
        $this->assertSame('900.00', $rows[0]->amount->getAmount());
        $this->assertSame('100.00', $rows[0]->bookValueEnd->getAmount());
    }

    /** Rundung je Zeile, aber die Summe trifft AK − RW exakt. */
    public function test_rounded_rows_sum_up_exactly_to_the_depreciable_base(): void {
        $rows = (new DepreciationCalculator)->scheduleFor($this->asset('2026-03-15', '1000.00', 36));

        $this->assertSame([10, 12, 12, 2], array_map(fn ($row): int => $row->months, $rows));
        $this->assertSame('277.78', $rows[0]->amount->getAmount());
        $this->assertSame('333.33', $rows[1]->amount->getAmount());
        $this->assertSame('55.56', $rows[3]->amount->getAmount());
        $this->assertSame('1000.00', $this->sum($rows));
        $this->assertSame('0.00', $rows[3]->bookValueEnd->getAmount());
    }

    /** Der Abgang beendet den Plan im Abgangsmonat; der Restbuchwert bleibt stehen. */
    public function test_a_disposal_cuts_the_schedule_at_the_disposal_month(): void {
        $rows = (new DepreciationCalculator)->scheduleFor($this->asset('2026-01-01', '3600.00', 36, '0.00', '2027-05-15'));

        $this->assertCount(2, $rows);
        $this->assertSame('1200.00', $rows[0]->amount->getAmount());
        $this->assertSame(5, $rows[1]->months);
        $this->assertSame('500.00', $rows[1]->amount->getAmount());
        $this->assertSame('1900.00', $rows[1]->bookValueEnd->getAmount());
    }

    /** Abweichendes Geschäftsjahr (Juli–Juni): Schlüssel ist das Startjahr. */
    public function test_a_deviating_fiscal_year_is_keyed_by_its_start_year(): void {
        $rows = (new DepreciationCalculator)->scheduleFor($this->asset('2026-10-01', '1200.00', 12), 7);

        $this->assertCount(2, $rows);
        $this->assertSame(2026, $rows[0]->fiscalYear);
        $this->assertSame('2026/2027', $rows[0]->label);
        $this->assertSame(9, $rows[0]->months);
        $this->assertSame('900.00', $rows[0]->amount->getAmount());
        $this->assertSame('300.00', $rows[1]->amount->getAmount());
    }

    public function test_amount_for_year_outside_the_schedule_is_zero(): void {
        $calculator = new DepreciationCalculator;
        $asset = $this->asset('2026-10-01', '3600.00', 36);

        $this->assertSame('300.00', $calculator->amountForYear($asset, 2026)->getAmount());
        $this->assertSame('0.00', $calculator->amountForYear($asset, 2031)->getAmount());
        $this->assertNull($calculator->rowForYear($asset, 2025));
    }

    public function test_without_a_depreciable_base_there_is_no_schedule(): void {
        $this->assertSame([], (new DepreciationCalculator)->scheduleFor($this->asset('2026-01-01', '100.00', 12, '100.00')));
    }
}
