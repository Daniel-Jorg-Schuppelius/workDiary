<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsReportBuilderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Customer, Expense, MaterialUsage, Project, TimeEntry, Timesheet, User};
use App\Services\Reporting\EconomicsReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class EconomicsReportBuilderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private EconomicsReportBuilder $builder;

    private User $user;

    private Customer $customer;

    private Project $project;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->builder = app(EconomicsReportBuilder::class);

        $this->from = CarbonImmutable::parse('2026-06-01')->startOfDay();
        $this->to = CarbonImmutable::parse('2026-06-30')->endOfDay();

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Stundensatz 100 €/h, interner Kostensatz 40 €/h → DB pro Stunde 60 €.
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Profitabler Kunde',
            'hourly_rate' => 100,
            'internal_rate' => 40,
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Profitprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
            'time_budget' => 60,   // Plan: 60 Minuten
            'budget' => 200,       // Plan-Budget: 200 €
        ]);
    }

    private function makeTimeEntry(int $minutes, bool $billable, ?int $projectId = null): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $projectId ?? $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-10',
            'kind' => TimeEntryKind::Work->value,
            'minutes' => $minutes,
            'billable' => $billable,
        ]);
    }

    public function test_computes_revenue_cost_and_contribution_per_project(): void {
        // 120 Min abrechenbar @100/h = 200 € Erlös; intern @40/h = 80 € Kosten.
        $this->makeTimeEntry(120, true);
        // 30 Min nicht abrechenbar (Nacharbeit-Proxy): kein Erlös, 20 € Kosten.
        $this->makeTimeEntry(30, false);

        $rows = collect($this->builder->byProject($this->from, $this->to));
        $row = $rows->firstWhere('projectId', $this->project->id);
        $this->assertNotNull($row);

        $this->assertSame('Profitprojekt', $row['projectName']);
        $this->assertSame(120, $row['billableMinutes']);
        $this->assertSame(30, $row['nonBillableMinutes']);
        $this->assertSame(150, $row['totalMinutes']);
        $this->assertEqualsWithDelta(20.0, $row['nonBillableShare'], 0.01);

        $this->assertEqualsWithDelta(200.0, $row['revenue'], 0.01);
        $this->assertEqualsWithDelta(100.0, $row['cost'], 0.01); // 80 + 20
        $this->assertEqualsWithDelta(100.0, $row['contribution'], 0.01);
        $this->assertEqualsWithDelta(50.0, $row['margin'], 0.01); // 100/200
        $this->assertFalse($row['costRateMissing']);
    }

    public function test_includes_billed_material_and_billable_expense_in_revenue(): void {
        $this->makeTimeEntry(60, true); // 100 € Erlös, 40 € Kosten

        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-12',
            'status' => TimesheetStatus::Draft->value,
        ]);

        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Ersatzteil',
            'quantity' => 2,
            'unit' => 'Stk',
            'unit_price' => 25,
            'billed' => true,
        ]); // line_total_net = 50 €

        Expense::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'date' => '2026-06-12',
            'description' => 'Parkgebühr',
            'amount_net' => 10,
            'tax_rate' => 0,
            'billable' => true,
            'status' => ExpenseStatus::Approved->value,
        ]);

        $rows = collect($this->builder->byProject($this->from, $this->to));
        $row = $rows->firstWhere('projectId', $this->project->id);
        $this->assertNotNull($row);

        // Erlös: 100 (Zeit) + 50 (Material) + 10 (Spesen) = 160
        $this->assertEqualsWithDelta(160.0, $row['revenue'], 0.01);
        // Kosten: 40 (Zeit) + 50 (Material-Direktaufwand) + 10 (Spesen) = 100
        $this->assertEqualsWithDelta(100.0, $row['cost'], 0.01);
        $this->assertEqualsWithDelta(60.0, $row['contribution'], 0.01);
    }

    public function test_marks_cost_rate_missing_when_no_internal_rate(): void {
        $cheapCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Ohne Kostensatz',
            'hourly_rate' => 80,
            // kein internal_rate
        ]);
        $cheapProject = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $cheapCustomer->id,
            'name' => 'Projekt ohne Kostensatz',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $this->makeTimeEntry(60, true, $cheapProject->id);

        $rows = collect($this->builder->byProject($this->from, $this->to));
        $row = $rows->firstWhere('projectId', $cheapProject->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(0.0, $row['cost'], 0.01);
        $this->assertTrue($row['costRateMissing']);
    }

    public function test_customer_ranking_orders_by_contribution(): void {
        // Profitabler Kunde: 60 Min @100/40 → DB 60 €.
        $this->makeTimeEntry(60, true);

        // Defizitärer Kunde: internal_rate > hourly_rate → negativer DB.
        $lossCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Defizitkunde',
            'hourly_rate' => 30,
            'internal_rate' => 90,
        ]);
        $lossProject = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $lossCustomer->id,
            'name' => 'Defizitprojekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $this->makeTimeEntry(60, true, $lossProject->id);

        $rows = collect($this->builder->byCustomer($this->from, $this->to));

        $top = $rows->sortByDesc('contribution')->first();
        $flop = $rows->sortBy('contribution')->first();

        $this->assertSame('Profitabler Kunde', $top['customerName']);
        $this->assertSame('Defizitkunde', $flop['customerName']);
        $this->assertLessThan(0.0, $flop['contribution']); // 30 - 90 = -60 €
    }

    public function test_plan_vs_actual_minutes_and_budget(): void {
        // Ist: 90 Min, Plan: 60 Min → Δ +30. Kosten 90/60*40 = 60 €, Budget 200 → Δ -140.
        $this->makeTimeEntry(90, true);

        $rows = collect($this->builder->byProject($this->from, $this->to));
        $row = $rows->firstWhere('projectId', $this->project->id);
        $this->assertNotNull($row);

        $this->assertSame(60, $row['planMinutes']);
        $this->assertSame(90, $row['actualMinutes']);
        $this->assertSame(30, $row['planMinutesDelta']);
        $this->assertEqualsWithDelta(200.0, $row['planBudget'], 0.01);
        $this->assertEqualsWithDelta(60.0, $row['actualCost'], 0.01);
        $this->assertEqualsWithDelta(-140.0, $row['planBudgetDelta'], 0.01);
    }
}
