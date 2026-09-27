<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProgramTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentProgram};
use App\Models\Platform\User;
use App\Services\Investments\InvestmentProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-927: Investitionsprogramme mit Jahresbudgets und Portfolio. */
final class InvestmentProgramTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function case(string $title, array $extra, string $amount, string $status): InvestmentCase {
        $case = InvestmentCase::query()->create(['organization_id' => $this->organization->id, 'title' => $title, 'category' => 'machine', 'status' => 'budget_request', 'created_by' => $this->admin->id] + $extra);
        InvestmentBudgetRequest::query()->create(['organization_id' => $this->organization->id, 'investment_case_id' => $case->id, 'version' => 1, 'amount' => $amount, 'cost_kind' => 'purchase', 'financing' => 'cash', 'status' => $status, 'requested_by' => $this->admin->id]);

        return $case;
    }

    public function test_programme_compares_planned_values_per_year_with_budgets(): void {
        $this->actingAs($this->admin)->post(route('investments.programs.store'), ['name' => 'Werkstatt 2030', 'starts_year' => 2026, 'ends_year' => 2028, 'currency' => 'EUR'])->assertRedirect();
        $program = InvestmentProgram::query()->sole();
        $this->actingAs($this->admin)->put(route('investments.programs.budgets', $program), ['budget' => [2026 => '100000', 2027 => '50000', 2028 => '']])->assertSessionHas('success');
        $this->assertSame(['2026' => '100000.00', '2027' => '50000.00'], $program->budgets()->pluck('budget_amount', 'year')->map(fn ($v): string => (string) $v)->mapWithKeys(fn ($v, $k): array => [(string) $k => $v])->all());

        $press = $this->case('Presse', ['investment_program_id' => $program->id, 'planned_year' => 2026], '80000.00', 'approved');
        $this->case('Kran', ['investment_program_id' => $program->id, 'starts_on' => '2026-05-01'], '30000.00', 'draft');
        $this->case('Rejected', ['investment_program_id' => $program->id, 'planned_year' => 2027], '999.00', 'rejected');

        $portfolio = app(InvestmentProgramService::class)->portfolio($program);
        $year2026 = collect($portfolio['years'])->firstWhere('year', 2026);
        $this->assertSame('110000.00', $year2026['planned']);
        $this->assertSame(80000.0, $year2026['approved']);
        $this->assertTrue($year2026['over']);
        $this->assertSame('0.00', collect($portfolio['years'])->firstWhere('year', 2027)['planned']);

        $this->actingAs($this->admin)->get(route('investments.programs.show', $program))->assertOk()->assertSee('Presse')->assertSee('110.000,00');
        $this->actingAs($this->admin)->get(route('investments.edit', $press))->assertOk()->assertSee('Werkstatt 2030');
    }

    public function test_programme_status_follows_its_contract_and_needs_manage(): void {
        $program = InvestmentProgram::query()->create(['organization_id' => $this->organization->id, 'name' => 'P', 'starts_year' => 2026, 'ends_year' => 2027, 'currency' => 'EUR', 'status' => 'planning']);
        $this->actingAs($this->admin)->post(route('investments.programs.status', $program), ['status' => 'closed'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('investments.programs.status', $program), ['status' => 'planning'])->assertStatus(422);

        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('investments.programs.index'))->assertForbidden();
    }
}
