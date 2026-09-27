<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentFinancingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Models\Investments\{InvestmentCase, InvestmentFinancingVariant, InvestmentOption};
use App\Models\Platform\User;
use App\Services\Investments\FinancingComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-907: Finanzierungsvergleich Kauf/Kredit/Leasing je Investitionsvariante. */
final class InvestmentFinancingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private InvestmentCase $case;

    private InvestmentOption $option;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->case = InvestmentCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Transporter', 'category' => 'machine', 'status' => 'comparison', 'created_by' => $this->admin->id]);
        $this->option = InvestmentOption::query()->create(['organization_id' => $this->organization->id, 'investment_case_id' => $this->case->id, 'title' => 'Kastenwagen', 'one_time_cost' => '10000.00', 'recurring_cost_yearly' => '0']);
    }

    public function test_variants_are_compared_against_purchase(): void {
        $route = route('investments.options.financing', [$this->case, $this->option]);
        $this->actingAs($this->admin)->get(route('investments.show', $this->case))->assertOk()->assertSee($route, false);

        $store = route('investments.options.financing.store', [$this->case, $this->option]);
        $this->actingAs($this->admin)->post($store, ['kind' => 'purchase'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($store, ['kind' => 'loan', 'interest_rate' => '5', 'term_months' => 36])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($store, ['kind' => 'lease', 'term_months' => 36, 'rate_amount' => '250', 'down_payment_amount' => '1000', 'residual_amount' => '2000', 'fee_amount' => '150'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post($store, ['kind' => 'loan', 'term_months' => 36])->assertSessionHasErrors('interest_rate');

        $rows = app(FinancingComparison::class)->evaluate($this->option->load('financingVariants'));
        $this->assertSame(['10000.00', '0.00'], [$rows[0]['total'], $rows[0]['extra']]);
        $this->assertSame('299.71', $rows[1]['monthly']);
        $this->assertSame($rows[1]['interest'], $rows[1]['extra'], 'Mehrkosten des Kredits = Zinsen');
        $paid = array_reduce($rows[1]['schedule'], static fn (string $sum, array $line): string => bcadd($sum, $line['payment'], 2), '0.00');
        $this->assertSame($paid, $rows[1]['total'], 'Gesamtkosten = Summe der Raten');
        $this->assertSame('10789.54', $rows[1]['total']);
        $this->assertSame(['250.00', '12150.00', '2150.00'], [$rows[2]['monthly'], $rows[2]['total'], $rows[2]['extra']]);

        $this->actingAs($this->admin)->get($route)->assertOk()->assertSee('299,71 €')->assertSee('12.150,00 €')->assertSee(__('investment.financing.schedule'));

        $lease = InvestmentFinancingVariant::query()->where('kind', 'lease')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('investments.options.financing.destroy', [$this->case, $this->option, $lease]))->assertRedirect($route);
        $this->assertSame(2, InvestmentFinancingVariant::query()->count());
    }
}
