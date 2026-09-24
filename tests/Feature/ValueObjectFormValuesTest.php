<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueObjectFormValuesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Material\Material;
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Models\Time\MinimumWage;
use App\Models\Travel\{Expense, ExpenseCategory, PerDiemRate};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wertobjekt-Audit 2026-09-19: Formulare gaben Money/Percentage-Casts per
 * __toString aus („85.00 EUR", „19.00 %"). Ein type=number-Feld verwirft
 * solche Werte, das Speichern schickte leer zurück — optionale Sätze wurden
 * gelöscht, Pflichtfelder scheiterten an der Validierung. Die Felder müssen
 * die rohe Dezimalzahl tragen.
 */
class ValueObjectFormValuesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function assertNumericValue(string $url, string $expected, ?User $as = null): void {
        $this->actingAs($as ?? $this->admin)
            ->get($url)
            ->assertOk()
            ->assertSee('value="' . $expected . '"', false)
            ->assertDontSee($expected . ' EUR', false)
            ->assertDontSee($expected . ' %', false);
    }

    public function test_customer_rates(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => '85.00',
            'internal_rate' => '42.50',
        ]);

        $this->assertNumericValue(route('customers.edit', $customer), '85.00');
        $this->assertNumericValue(route('customers.edit', $customer), '42.50');
    }

    public function test_task_rates_in_global_and_project_dialog(): void {
        $global = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => null,
            'is_global' => true,
            'hourly_rate' => '95.00',
        ]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'budget' => '1200.00',
        ]);

        $this->assertNumericValue(route('tasks.global.edit', $global), '95.00');
        $this->assertNumericValue(route('projects.tasks.edit', [$project, $task]), '1200.00');
    }

    public function test_article_prices(): void {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_purchase_price' => '12.3400',
            'default_sale_price' => '19.9900',
        ]);

        $this->assertNumericValue(route('articles.edit', $article), '12.3400');
        $this->assertNumericValue(route('articles.edit', $article), '19.9900');
    }

    public function test_material_price_and_tax_rate(): void {
        $material = Material::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kabelbinder',
            'unit' => 'Stk',
            'default_unit_price' => '0.2500',
            'tax_rate' => '7.00',
        ]);

        $this->assertNumericValue(route('materials.edit', $material), '0.2500');
        $this->assertNumericValue(route('materials.edit', $material), '7.00');
    }

    public function test_per_diem_amounts(): void {
        $rate = PerDiemRate::factory()->create([
            'full_day_amount' => '28.00',
            'partial_day_amount' => '14.00',
            'overnight_amount' => '20.00',
        ]);

        // Tagessätze sind installationsweit — nur für Plattform-Betreiber.
        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->assertNumericValue(route('admin.per-diem-rates.edit', $rate), '28.00', $operator);
        $this->assertNumericValue(route('admin.per-diem-rates.edit', $rate), '20.00', $operator);
    }

    public function test_expense_category_default_tax_rate_and_expense_amounts(): void {
        $category = ExpenseCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'default_tax_rate' => '7.00',
        ]);
        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'expense_category_id' => $category->id,
            'amount_gross' => '107.00',
            'tax_rate' => '7.00',
        ]);

        $this->assertNumericValue(route('admin.expense-categories.edit', $category), '7.00');
        // Das Model rechnet Beträge beim Speichern nach — erwartet wird der gespeicherte Bruttowert.
        $this->assertNumericValue(route('expenses.edit', $expense), (string) $expense->fresh()?->amount_gross?->getAmount());
        // Das Kategorie-Default füllt per JS den Steuersatz vor — dafür muss es numerisch sein.
        $this->actingAs($this->admin)->get(route('expenses.edit', $expense))->assertSee('data-tax-rate="7.00"', false);
    }

    /** Das Mitgliederformular crashte zusätzlich am Mindestlohn-Vergleich (float) $wageVal. */
    public function test_member_payroll_wage_with_minimum_wage(): void {
        MinimumWage::factory()->create([
            'organization_id' => $this->organization->id,
            'valid_from' => now()->subYear()->toDateString(),
            'hourly_amount' => '12.82',
        ]);
        $member = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'payroll_hourly_wage' => '15.50',
        ]);

        $this->assertNumericValue(route('org.members.edit', $member), '15.50');
    }
}
