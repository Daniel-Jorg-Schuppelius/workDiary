<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpensePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, User};
use App\Policies\ExpensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Spesen: eigentümergebunden (owns) mit Status-Maschine — ändern/löschen nur
 * bis zur endgültigen Entscheidung, einreichen nur aus Draft/Rejected.
 * Genehmigen (decide) und Erstatten (reimburse) sind auf Policy-Ebene hart
 * false und laufen ausschließlich über den Admin-Bypass (HasAdminBypass).
 */
final class ExpensePolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private ExpensePolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new ExpensePolicy;
    }

    private function expense(User $owner, ExpenseStatus $status): Expense {
        $expense = new Expense;
        $expense->user_id = $owner->id;
        $expense->status = $status;

        return $expense;
    }

    public function test_owner_may_edit_and_submit_until_decided(): void {
        $owner = $this->actorIn($this->organization);
        $draft = $this->expense($owner, ExpenseStatus::Draft);
        $rejected = $this->expense($owner, ExpenseStatus::Rejected);

        $this->assertTrue($this->policy->view($owner, $draft));
        $this->assertTrue($this->policy->update($owner, $draft));
        $this->assertTrue($this->policy->delete($owner, $draft));
        $this->assertTrue($this->policy->submit($owner, $draft));
        $this->assertTrue($this->policy->submit($owner, $rejected), 'Abgelehnte Spesen dürfen erneut eingereicht werden.');
        $this->assertTrue($this->policy->cancel($owner, $draft));
    }

    public function test_owner_cannot_touch_finally_decided_expenses(): void {
        $owner = $this->actorIn($this->organization);
        $approved = $this->expense($owner, ExpenseStatus::Approved);
        $reimbursed = $this->expense($owner, ExpenseStatus::Reimbursed);

        $this->assertFalse($this->policy->update($owner, $approved));
        $this->assertFalse($this->policy->delete($owner, $approved));
        $this->assertFalse($this->policy->submit($owner, $approved), 'Pending/Approved sind nicht erneut einreichbar.');
        $this->assertFalse($this->policy->cancel($owner, $reimbursed), 'Finale Spesen sind nicht stornierbar.');
    }

    public function test_non_owner_has_no_access(): void {
        $owner = $this->actorIn($this->organization);
        $other = $this->actorIn($this->organization);
        $draft = $this->expense($owner, ExpenseStatus::Draft);

        $this->assertFalse($this->policy->view($other, $draft));
        $this->assertFalse($this->policy->update($other, $draft));
        $this->assertFalse($this->policy->submit($other, $draft));
    }

    public function test_decide_and_reimburse_are_admin_only_via_bypass(): void {
        $owner = $this->actorIn($this->organization);
        $pending = $this->expense($owner, ExpenseStatus::Pending);

        // Selbst der Eigentümer darf nicht entscheiden/erstatten.
        $this->assertFalse($this->policy->decide($owner, $pending));
        $this->assertFalse($this->policy->reimburse($owner, $pending));

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actAsTeam($this->organization);
        $this->assertTrue(Gate::forUser($admin)->allows('decide', $pending));
        $this->assertTrue(Gate::forUser($admin)->allows('reimburse', $pending));
    }
}
