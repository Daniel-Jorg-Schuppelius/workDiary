<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemCalculatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Expense;

use App\Enums\Expense\PerDiemDayKind;
use App\Models\PerDiemRate;
use App\Models\PerDiemTrip;
use App\Models\User;
use App\Services\Expense\PerDiemCalculator;
use Database\Seeders\PerDiemRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PerDiemCalculatorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private PerDiemCalculator $calculator;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(PerDiemRateSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->calculator = app(PerDiemCalculator::class);
    }

    public function test_multi_day_trip_yields_departure_full_return(): void {
        $trip = PerDiemTrip::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'country' => 'DE',
            'purpose' => 'Workshop',
            'location' => 'Frankfurt',
            'started_at' => '2025-03-10 08:00:00',
            'ended_at' => '2025-03-12 18:00:00',
        ]);

        $days = $this->calculator->buildDays($trip);
        $this->assertCount(3, $days);
        $this->assertSame(PerDiemDayKind::DepartureDay, $days[0]->kind);
        $this->assertSame(PerDiemDayKind::FullDay, $days[1]->kind);
        $this->assertSame(PerDiemDayKind::ReturnDay, $days[2]->kind);

        $total = array_sum(array_map(fn ($d) => (float) $d->amount, $days));
        $this->assertEqualsWithDelta(14.0 + 28.0 + 14.0, $total, 0.001);
    }

    public function test_single_day_over_eight_hours_returns_partial(): void {
        $trip = PerDiemTrip::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'country' => 'DE',
            'purpose' => 'Termin',
            'location' => 'Hannover',
            'started_at' => '2025-03-10 07:00:00',
            'ended_at' => '2025-03-10 19:00:00',
        ]);

        $days = $this->calculator->buildDays($trip);
        $this->assertCount(1, $days);
        $this->assertSame(PerDiemDayKind::SingleDay, $days[0]->kind);
        $this->assertEqualsWithDelta(14.0, (float) $days[0]->amount, 0.001);
    }

    public function test_short_single_day_yields_no_days(): void {
        $trip = PerDiemTrip::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'country' => 'DE',
            'purpose' => 'Kurzbesuch',
            'location' => 'Köln',
            'started_at' => '2025-03-10 09:00:00',
            'ended_at' => '2025-03-10 14:00:00',
        ]);

        $this->assertCount(0, $this->calculator->buildDays($trip));
    }

    public function test_meal_deductions_reduce_full_day(): void {
        $trip = PerDiemTrip::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'country' => 'DE',
            'purpose' => 'Workshop',
            'location' => 'Frankfurt',
            'started_at' => '2025-03-10 08:00:00',
            'ended_at' => '2025-03-10 22:00:00',
        ]);

        $days = $this->calculator->buildDays($trip);
        $day = $days[0];
        // Single full-day partial amount = 14, deductions taken from full_day_amount (28)
        $day->meal_breakfast = true; // -20% of 28 = 5.60
        $day->meal_lunch = true;     // -40% of 28 = 11.20
        $this->calculator->recalculateDay($day);

        $this->assertEqualsWithDelta(16.80, (float) $day->deductions_total, 0.001);
        // amount must not go below 0
        $this->assertGreaterThanOrEqual(0, (float) $day->amount);
    }

    public function test_missing_rate_throws(): void {
        PerDiemRate::query()->delete();

        $trip = PerDiemTrip::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'country' => 'DE',
            'purpose' => 'Workshop',
            'location' => 'Berlin',
            'started_at' => '2025-03-10 08:00:00',
            'ended_at' => '2025-03-11 18:00:00',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->calculator->buildDays($trip);
    }
}
