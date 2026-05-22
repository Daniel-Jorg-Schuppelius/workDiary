<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseApprovalBulkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ExpenseApprovalBulkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    private ExpenseCategory $category;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
        Notification::fake();
    }

    private function makeExpense(ExpenseStatus $status = ExpenseStatus::Pending): Expense {
        return Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'status' => $status,
        ]);
    }

    public function test_bulk_approve_decides_all_pending(): void {
        $a = $this->makeExpense();
        $b = $this->makeExpense();
        $c = $this->makeExpense();

        $this->actingAs($this->admin)
            ->post(route('expense-approvals.bulk-approve'), ['ids' => [$a->id, $b->id, $c->id]])
            ->assertRedirect(route('expense-approvals.inbox'));

        $this->assertSame(ExpenseStatus::Approved, $a->fresh()->status);
        $this->assertSame(ExpenseStatus::Approved, $b->fresh()->status);
        $this->assertSame(ExpenseStatus::Approved, $c->fresh()->status);
    }

    public function test_bulk_reject_with_reason(): void {
        $a = $this->makeExpense();
        $b = $this->makeExpense();

        $this->actingAs($this->admin)
            ->post(route('expense-approvals.bulk-reject'), [
                'ids' => [$a->id, $b->id],
                'reject_reason' => 'Beleg fehlt',
            ])
            ->assertRedirect(route('expense-approvals.inbox'));

        $this->assertSame(ExpenseStatus::Rejected, $a->fresh()->status);
        $this->assertSame(ExpenseStatus::Rejected, $b->fresh()->status);
        $this->assertSame('Beleg fehlt', $a->fresh()->reject_reason);
    }

    public function test_bulk_skips_non_pending(): void {
        $pending = $this->makeExpense();
        $draft = $this->makeExpense(ExpenseStatus::Draft);

        $this->actingAs($this->admin)
            ->post(route('expense-approvals.bulk-approve'), ['ids' => [$pending->id, $draft->id]])
            ->assertRedirect();

        $this->assertSame(ExpenseStatus::Approved, $pending->fresh()->status);
        $this->assertSame(ExpenseStatus::Draft, $draft->fresh()->status);
    }

    public function test_bulk_requires_ids(): void {
        $this->actingAs($this->admin)
            ->post(route('expense-approvals.bulk-approve'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_forbidden_for_non_approver(): void {
        $pending = $this->makeExpense();

        $this->actingAs($this->user)
            ->post(route('expense-approvals.bulk-approve'), ['ids' => [$pending->id]])
            ->assertRedirect();

        $this->assertSame(ExpenseStatus::Pending, $pending->fresh()->status);
    }
}
