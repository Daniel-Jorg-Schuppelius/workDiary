<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AutoApproveExpenseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Automation;

use App\Enums\Expense\ExpenseStatus;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Expense\ExpenseService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AutoApproveExpenseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    private ExpenseCategory $category;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
        Notification::fake();
    }

    public function test_low_value_expense_is_auto_approved(): void {
        AutomationRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Auto-Approve Kleinbeträge',
            'trigger_event' => 'expense.submitted',
            'conditions' => [
                'all' => [
                    ['field' => 'amount_gross', 'op' => '<=', 'value' => 50],
                ],
            ],
            'actions' => [
                ['type' => 'expense.approve', 'params' => []],
            ],
            'is_active' => true,
            'priority' => 10,
        ]);

        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'amount_net' => '30.00',
            'tax_rate' => '0.00',
            'status' => ExpenseStatus::Draft->value,
        ]);

        app(ExpenseService::class)->submitForApproval($expense);
        $expense->refresh();

        $this->assertSame(ExpenseStatus::Approved, $expense->status);
        $this->assertSame(1, AutomationRuleRun::query()->where('decision', 'matched')->count());
    }

    public function test_expense_above_threshold_remains_pending(): void {
        AutomationRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Auto-Approve Kleinbeträge',
            'trigger_event' => 'expense.submitted',
            'conditions' => [
                'all' => [
                    ['field' => 'amount_gross', 'op' => '<=', 'value' => 50],
                ],
            ],
            'actions' => [
                ['type' => 'expense.approve', 'params' => []],
            ],
            'is_active' => true,
            'priority' => 10,
        ]);

        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'amount_net' => '200.00',
            'tax_rate' => '0.00',
            'status' => ExpenseStatus::Draft->value,
        ]);

        app(ExpenseService::class)->submitForApproval($expense);
        $expense->refresh();

        $this->assertSame(ExpenseStatus::Pending, $expense->status);
        $this->assertSame(1, AutomationRuleRun::query()->where('decision', 'no_match')->count());
    }

    public function test_inactive_rule_is_skipped(): void {
        AutomationRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Disabled Rule',
            'trigger_event' => 'expense.submitted',
            'conditions' => ['all' => [['field' => 'amount_gross', 'op' => '<=', 'value' => 99999]]],
            'actions' => [['type' => 'expense.approve', 'params' => []]],
            'is_active' => false,
            'priority' => 10,
        ]);

        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'amount_net' => '10.00',
            'tax_rate' => '0.00',
            'status' => ExpenseStatus::Draft->value,
        ]);

        app(ExpenseService::class)->submitForApproval($expense);

        $this->assertSame(ExpenseStatus::Pending, $expense->fresh()?->status);
        $this->assertSame(0, AutomationRuleRun::query()->count());
    }
}
