<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseApprovalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Expense\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use App\Services\Expense\ExpenseService;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseApprovalController extends Controller {
    public function __construct(
        private readonly ExpenseService $service,
    ) {
    }

    public function inbox(Request $request): View {
        // Liste aller offenen / kürzlich entschiedenen Spesen — nur Admin-bypass darf rein.
        $sample = new Expense;
        Gate::authorize('decide', $sample);

        $statusFilter = $request->string('status')->toString();
        $statusEnum = ($statusFilter !== '' ? ExpenseStatus::tryFrom($statusFilter) : null) ?? ExpenseStatus::Pending;

        /** @var User $auth */
        $auth = Auth::user();

        $query = Expense::query()
            ->with(['user:id,name,email', 'category:id,label,color,icon', 'project:id,name', 'customer:id,name'])
            ->where('status', $statusEnum->value);

        if ($auth->organization_id !== null) {
            $query->where('organization_id', $auth->organization_id);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'date' => 'date',
            'owner' => 'user_id',
            'amount' => 'amount_gross',
            'status' => 'status',
        ], 'date', 'asc');

        $expenses = $query->paginate(25)->withQueryString();

        $counts = [
            ExpenseStatus::Pending->value => Expense::query()
                ->when($auth->organization_id !== null, fn($q) => $q->where('organization_id', $auth->organization_id))
                ->where('status', ExpenseStatus::Pending->value)
                ->count(),
            ExpenseStatus::Approved->value => Expense::query()
                ->when($auth->organization_id !== null, fn($q) => $q->where('organization_id', $auth->organization_id))
                ->where('status', ExpenseStatus::Approved->value)
                ->count(),
        ];

        return view('expenses.inbox', [
            'expenses' => $expenses,
            'sort' => $sort,
            'dir' => $dir,
            'statusEnum' => $statusEnum,
            'statusOptions' => [
                ExpenseStatus::Pending,
                ExpenseStatus::Approved,
                ExpenseStatus::Rejected,
                ExpenseStatus::Reimbursed,
            ],
            'counts' => $counts,
        ]);
    }

    public function approve(Expense $expense): RedirectResponse {
        Gate::authorize('decide', $expense);

        /** @var User $approver */
        $approver = Auth::user();
        $this->service->approve($expense, $approver);

        return redirect()->route('expense-approvals.inbox')
            ->with('success', __('Spese genehmigt.'));
    }

    public function rejectForm(Expense $expense): View {
        Gate::authorize('decide', $expense);

        return view('expenses._reject_dialog', [
            'expense' => $expense,
        ]);
    }

    public function reject(Request $request, Expense $expense): RedirectResponse {
        Gate::authorize('decide', $expense);

        $data = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $approver */
        $approver = Auth::user();
        $this->service->reject($expense, $approver, $data['reject_reason'] ?? null);

        return redirect()->route('expense-approvals.inbox')
            ->with('success', __('Spese abgelehnt.'));
    }

    public function markReimbursed(Request $request, Expense $expense): RedirectResponse {
        Gate::authorize('reimburse', $expense);

        $data = $request->validate([
            'reimbursement_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $this->service->markReimbursed($expense, $data['reimbursement_reference'] ?? null);

        return redirect()->route('expense-approvals.inbox', ['status' => ExpenseStatus::Approved->value])
            ->with('success', __('Spese als erstattet markiert.'));
    }
}
