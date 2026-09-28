<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecliningDepreciationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Finance;

use App\Enums\Finance\{DepreciationMethod, FixedAssetStatus};
use App\Models\Accounting\FixedAsset;
use App\Services\Accounting\{DepreciationCalculator, FixedAssetService};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Tests\TestCase;

/** MVP-980: degressive AfA mit Wechsel zur linearen AfA und gesetzlichen Höchstsätzen. */
final class DecliningDepreciationTest extends TestCase {
    private function asset(string $rate): FixedAsset {
        $asset = new FixedAsset;
        $asset->forceFill([
            'name' => 'Bagger', 'acquired_on' => '2026-01-01', 'currency' => 'EUR', 'acquisition_cost' => '10000.00',
            'residual_value' => '0.00', 'useful_life_months' => 120, 'depreciation_method' => DepreciationMethod::Declining,
            'declining_rate' => $rate, 'status' => FixedAssetStatus::Active,
        ]);

        return $asset;
    }

    public function test_rate_applies_to_the_book_value_until_straight_line_is_higher(): void {
        $rows = (new DepreciationCalculator)->scheduleFor($this->asset('30.00'));

        $amounts = array_map(static fn ($row): string => $row->amount->getAmount(), $rows);
        $this->assertCount(10, $rows);
        $this->assertSame(['3000.00', '2100.00', '1470.00', '1029.00', '720.30', '504.21', '352.95'], array_slice($amounts, 0, 7));
        // 2033: linear auf den Rest (823,54 / 36 × 12 = 274,51) liegt über 247,06 degressiv.
        $this->assertSame('274.51', $amounts[7]);
        $this->assertSame('0.00', $rows[9]->bookValueEnd->getAmount());
        $this->assertSame('10000.00', Money::sum(array_map(static fn ($row): Money => $row->amount, $rows))->getAmount());
    }

    public function test_statutory_windows_cap_the_rate(): void {
        $this->assertSame('30.00', FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2026-03-01'), 120)?->getNumericValue());
        $this->assertSame('30.00', FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2026-03-01'), 36)?->getNumericValue());
        $this->assertSame('20.00', FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2024-06-01'), 120)?->getNumericValue());
        $this->assertSame('25.00', FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2021-05-01'), 60)?->getNumericValue());
        $this->assertSame('7.50', FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2021-05-01'), 400)?->getNumericValue());
        $this->assertNull(FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2023-06-01'), 120));
        $this->assertNull(FixedAssetService::maxDecliningRate(CarbonImmutable::parse('2025-06-30'), 120));
    }
}
