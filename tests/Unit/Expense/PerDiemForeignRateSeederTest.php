<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemForeignRateSeederTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Expense;

use App\Models\PerDiemRate;
use App\Services\Expense\PerDiemRateLookup;
use Carbon\CarbonImmutable;
use Database\Seeders\PerDiemForeignRateSeeder;
use Database\Seeders\PerDiemRateSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PerDiemForeignRateSeederTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private PerDiemRateLookup $lookup;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->seed(PerDiemRateSeeder::class);
        $this->seed(PerDiemForeignRateSeeder::class);
        $this->lookup = app(PerDiemRateLookup::class);
    }

    public function test_seeder_creates_country_default_rates(): void {
        $fr = PerDiemRate::query()
            ->where('country', 'FR')
            ->whereNull('region_label')
            ->whereDate('valid_from', '2025-01-01')
            ->first();

        $this->assertNotNull($fr);
        $this->assertSame('50.00', $fr->full_day_amount);
        $this->assertSame('33.00', $fr->partial_day_amount);
        $this->assertSame('123.00', $fr->overnight_amount);
        $this->assertSame('EUR', $fr->currency);
    }

    public function test_seeder_creates_region_specific_rates(): void {
        $london = PerDiemRate::query()
            ->where('country', 'GB')
            ->where('region_label', 'London')
            ->whereDate('valid_from', '2025-01-01')
            ->first();

        $this->assertNotNull($london);
        $this->assertSame('66.00', $london->full_day_amount);
        $this->assertSame('235.00', $london->overnight_amount);
    }

    public function test_lookup_returns_region_match_when_region_provided(): void {
        $rate = $this->lookup->for('US', CarbonImmutable::parse('2025-06-15'), 'New York');

        $this->assertNotNull($rate);
        $this->assertSame('New York', $rate->region_label);
        $this->assertSame('72.00', $rate->full_day_amount);
    }

    public function test_lookup_falls_back_to_country_default_for_unknown_region(): void {
        $rate = $this->lookup->for('US', CarbonImmutable::parse('2025-06-15'), 'Phoenix');

        $this->assertNotNull($rate);
        $this->assertNull($rate->region_label);
        $this->assertSame('64.00', $rate->full_day_amount);
    }

    public function test_lookup_without_region_returns_country_default(): void {
        $rate = $this->lookup->for('FR', CarbonImmutable::parse('2025-06-15'));

        $this->assertNotNull($rate);
        $this->assertNull($rate->region_label);
        $this->assertSame('50.00', $rate->full_day_amount);
    }

    public function test_seeder_is_idempotent(): void {
        $countBefore = PerDiemRate::query()->where('country', 'FR')->count();

        $this->seed(PerDiemForeignRateSeeder::class);

        $countAfter = PerDiemRate::query()->where('country', 'FR')->count();
        $this->assertSame($countBefore, $countAfter);
    }
}
