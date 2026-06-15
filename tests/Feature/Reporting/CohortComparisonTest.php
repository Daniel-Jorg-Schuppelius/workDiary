<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CohortComparisonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Qualification, TimeEntry, User};
use App\Services\Reporting\CohortComparisonBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class CohortComparisonTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function entry(int $userId, string $date, int $minutes, bool $billable): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $userId,
            'date' => $date,
            'kind' => TimeEntryKind::Work->value,
            'minutes' => $minutes,
            'billable' => $billable,
        ]);
    }

    public function test_builder_computes_before_after_billable_rate(): void {
        $employee = User::factory()->create(['organization_id' => $this->organization->id]);
        $acquired = CarbonImmutable::today()->subDays(10);

        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $employee->qualifications()->attach($qualification->id, [
            'valid_from' => $acquired->toDateString(),
            'valid_until' => null,
        ]);

        // BEFORE window (acquired-90 .. acquired-1): 50 % billable.
        $this->entry($employee->id, $acquired->subDays(20)->toDateString(), 60, true);
        $this->entry($employee->id, $acquired->subDays(20)->toDateString(), 60, false);

        // AFTER window (acquired .. acquired+89): 100 % billable.
        $this->entry($employee->id, $acquired->addDays(2)->toDateString(), 120, true);

        $result = app(CohortComparisonBuilder::class)->build($qualification, 'billableRate', 90);

        $this->assertCount(1, $result['members']);
        $member = $result['members'][0];
        $this->assertSame($acquired->toDateString(), $member['acquiredOn']);
        $this->assertSame(50.0, $member['before']);
        $this->assertSame(100.0, $member['after']);
        $this->assertSame(50.0, $member['delta']);
        $this->assertTrue($member['improved']);

        $this->assertSame(1, $result['aggregate']['membersWithDate']);
        $this->assertSame(1, $result['aggregate']['improvedCount']);
        $this->assertSame(50.0, $result['aggregate']['delta']);
    }

    public function test_rework_share_improvement_is_decrease(): void {
        $employee = User::factory()->create(['organization_id' => $this->organization->id]);
        $acquired = CarbonImmutable::today()->subDays(10);

        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $employee->qualifications()->attach($qualification->id, [
            'valid_from' => $acquired->toDateString(),
            'valid_until' => null,
        ]);

        // BEFORE: 50 % rework (non-billable).
        $this->entry($employee->id, $acquired->subDays(5)->toDateString(), 60, true);
        $this->entry($employee->id, $acquired->subDays(5)->toDateString(), 60, false);
        // AFTER: 0 % rework.
        $this->entry($employee->id, $acquired->addDays(1)->toDateString(), 90, true);

        $result = app(CohortComparisonBuilder::class)->build($qualification, 'reworkShare', 90);
        $member = $result['members'][0];

        $this->assertSame(50.0, $member['before']);
        $this->assertSame(0.0, $member['after']);
        $this->assertSame(-50.0, $member['delta']);
        $this->assertTrue($member['improved']); // decrease in rework = improvement
    }

    public function test_member_without_acquisition_date_is_reported_separately(): void {
        $employee = User::factory()->create(['organization_id' => $this->organization->id]);
        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $employee->qualifications()->attach($qualification->id, [
            'valid_from' => null,
            'valid_until' => null,
        ]);

        $result = app(CohortComparisonBuilder::class)->build($qualification, 'billableRate', 90);

        $this->assertSame(1, $result['aggregate']['membersWithoutDate']);
        $this->assertSame(0, $result['aggregate']['membersWithDate']);
        $this->assertNull($result['members'][0]['acquiredOn']);
        $this->assertNull($result['members'][0]['delta']);
    }

    public function test_report_route_renders_for_admin(): void {
        $employee = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Max Muster']);
        $acquired = CarbonImmutable::today()->subDays(10);
        $qualification = Qualification::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schulung X']);
        $employee->qualifications()->attach($qualification->id, ['valid_from' => $acquired->toDateString()]);
        $this->entry($employee->id, $acquired->addDays(1)->toDateString(), 60, true);

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession(now()->subDays(120)->toDateString(), now()->toDateString()))
            ->get(route('reports.cohort-comparison', [
                'qualification_id' => $qualification->sqid,
                'metric' => 'billableRate',
            ]));

        $response->assertOk();
        $response->assertSeeText('Max Muster');
    }

    public function test_report_forbidden_without_report_permission(): void {
        $plain = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeSession(now()->subDays(30)->toDateString(), now()->toDateString()))
            ->get(route('reports.cohort-comparison'))
            ->assertForbidden();
    }
}
