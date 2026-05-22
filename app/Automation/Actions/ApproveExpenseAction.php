<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApproveExpenseAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Automation\Actions;

use App\Models\Expense;
use App\Models\User;
use App\Services\Expense\ApproverResolver;
use App\Services\Expense\ExpenseService;
use Illuminate\Database\Eloquent\Model;

class ApproveExpenseAction implements RuleAction {
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly ApproverResolver $approverResolver,
    ) {
    }

    public function type(): string {
        return 'expense.approve';
    }

    /** @param array<string, mixed> $params */
    public function execute(Model $subject, array $params): array {
        if (! $subject instanceof Expense) {
            return ['skipped' => 'subject_not_expense'];
        }

        $approver = $this->systemApprover($subject);
        if (! $approver instanceof User) {
            return ['skipped' => 'no_approver_available'];
        }

        $this->expenses->approve($subject, $approver);

        return [
            'approved_by' => $approver->id,
            'approver_kind' => 'automation',
        ];
    }

    /**
     * Wählt einen technischen Approver: erster regulärer Approver der Org,
     * Fallback ist der Owner selbst (System-Decision, sichtbar im Audit).
     */
    private function systemApprover(Expense $expense): ?User {
        $approvers = $this->approverResolver->approversFor($expense);
        $first = $approvers->first();
        if ($first instanceof User) {
            return $first;
        }

        return $expense->user;
    }
}
