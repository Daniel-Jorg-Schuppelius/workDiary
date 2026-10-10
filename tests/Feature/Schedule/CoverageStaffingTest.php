<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageStaffingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Models\Platform\User;
use App\Models\Schedule\{CoverageRequirement, DutyPlan, ScheduledShift, ShiftType};
use App\Services\Schedule\CoverageService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Coverage-Auswertung und Dienstplan-Ampel rechnen gleich (MVP-1092):
 * Ist nur veröffentlicht/bestätigt, Plan-Mindestbesetzung als Rückfall,
 * „Immer“-Anforderung an jedem Tag.
 */
class CoverageStaffingTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private ShiftType $early;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->early = ShiftType::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Frühdienst']);
    }

    private function shift(?DutyPlan $plan, string $date, ScheduledShiftStatus $status): void {
        ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create(['organization_id' => $this->organization->id])->id,
            'duty_plan_id' => $plan?->id,
            'shift_type_id' => $this->early->id,
            'date' => $date,
            'status' => $status,
        ]);
    }

    public function test_plan_minimum_applies_and_only_published_shifts_count(): void {
        $plan = DutyPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'from_date' => '2026-11-02',
            'to_date' => '2026-11-08',
            'min_staff' => 2,
        ]);
        $this->shift($plan, '2026-11-03', ScheduledShiftStatus::Published);
        $this->shift($plan, '2026-11-03', ScheduledShiftStatus::Draft);

        $staffing = app(CoverageService::class)->staffingBetween(CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-08'));

        $this->assertSame(['min' => 2, 'actual' => 1], $staffing['2026-11-03'][$this->early->id]);
        $this->assertSame(2, $staffing['2026-11-07'][$this->early->id]['min']);
    }

    public function test_always_requirement_applies_every_day_below_weekday_rules(): void {
        CoverageRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'shift_type_id' => $this->early->id,
            'weekday' => null,
            'min_staff' => 1,
        ]);
        CoverageRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'shift_type_id' => $this->early->id,
            'weekday' => 6,
            'min_staff' => 3,
        ]);
        $this->shift(null, '2026-11-04', ScheduledShiftStatus::Confirmed);

        $staffing = app(CoverageService::class)->staffingBetween(CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-08'));

        $this->assertSame(['min' => 1, 'actual' => 1], $staffing['2026-11-04'][$this->early->id]);
        $this->assertSame(3, $staffing['2026-11-07'][$this->early->id]['min'], 'Samstag: Wochentagsregel schlägt „Immer“.');
    }

    public function test_coverage_report_uses_the_shared_rules(): void {
        $plan = DutyPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'from_date' => '2026-11-02',
            'to_date' => '2026-11-02',
            'min_staff' => 1,
        ]);
        $this->shift($plan, '2026-11-02', ScheduledShiftStatus::Draft);

        $this->actingAs(User::factory()->admin()->create(['organization_id' => $this->organization->id]))
            ->withSession($this->dateRangeMonth(2026, 11))
            ->get(route('reports.coverage'))
            ->assertOk()
            ->assertViewHas('totals', fn (array $totals): bool => $totals['required'] === 1 && $totals['scheduled'] === 0);
    }
}
