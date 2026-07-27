<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseWorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, ExpenseCategory, User};
use App\Notifications\Expense\{ExpenseDecidedNotification, ExpenseSubmittedNotification};
use App\Services\Expense\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ExpenseWorkflowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    private ExpenseCategory $category;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);

        Notification::fake();
    }

    public function test_index_renders(): void {
        $this->actingAs($this->user);
        $this->get(route('expenses.index'))->assertOk();
    }

    public function test_create_stores_expense_as_draft(): void {
        $this->actingAs($this->user);

        $service = app(ExpenseService::class);
        $expense = $service->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'date' => now()->toDateString(),
            'vendor' => 'Restaurant Müller',
            'description' => 'Geschäftsessen',
            'payment_method' => \App\Enums\Expense\PaymentMethod::PrivatePaid->value,
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
        ]);

        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertEquals(19.00, $expense->tax_amount?->toFloat());
        $this->assertEquals(119.00, $expense->amount_gross?->toFloat());
    }

    public function test_submit_changes_status_and_sends_notification(): void {
        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
        ]);

        app(ExpenseService::class)->submitForApproval($expense);

        $this->assertSame(ExpenseStatus::Pending, $expense->fresh()?->status);
        Notification::assertSentTo($this->admin, ExpenseSubmittedNotification::class);
    }

    public function test_approve_sets_status_and_decided_metadata(): void {
        $expense = Expense::factory()->pending()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
        ]);

        $approved = app(ExpenseService::class)->approve($expense, $this->admin);

        $this->assertSame(ExpenseStatus::Approved, $approved->status);
        $this->assertSame($this->admin->id, $approved->decided_by);
        $this->assertNotNull($approved->decided_at);
        Notification::assertSentTo($this->user, ExpenseDecidedNotification::class);
    }

    public function test_reject_stores_reason(): void {
        $expense = Expense::factory()->pending()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
        ]);

        $rejected = app(ExpenseService::class)->reject($expense, $this->admin, 'Beleg fehlt');

        $this->assertSame(ExpenseStatus::Rejected, $rejected->status);
        $this->assertSame('Beleg fehlt', $rejected->reject_reason);
    }

    public function test_mark_reimbursed_sets_timestamp_and_reference(): void {
        $expense = Expense::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
        ]);

        $reimbursed = app(ExpenseService::class)->markReimbursed($expense, 'TX-2026-001');

        $this->assertSame(ExpenseStatus::Reimbursed, $reimbursed->status);
        $this->assertNotNull($reimbursed->reimbursed_at);
        $this->assertSame('TX-2026-001', $reimbursed->reimbursement_reference);
    }

    public function test_owner_cannot_edit_after_approval(): void {
        $expense = Expense::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('expenses.edit', $expense))
            ->assertForbidden();
    }

    public function test_other_user_cannot_view_expense(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
        ]);

        $this->actingAs($other)
            ->get(route('expenses.edit', $expense))
            ->assertForbidden();
    }
}
