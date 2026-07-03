<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Expense\{ExpenseStatus, PaymentMethod};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveExpenseRequest;
use App\Models\{Customer, Expense, ExpenseCategory, Project, User};
use App\Services\Expense\ExpenseService;
use App\Support\SortableQuery;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly ExpenseService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Expense::class);

        [$from, $to] = $this->resolveRange($request);

        $statusFilter = $request->string('status')->toString();
        $statusEnum = $statusFilter !== '' ? ExpenseStatus::tryFrom($statusFilter) : null;

        $query = Expense::query()
            ->with(['category:id,label,color,icon', 'project:id,name', 'customer:id,name'])
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($statusEnum !== null) {
            $query->where('status', $statusEnum->value);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'date' => 'date',
            'vendor' => 'vendor',
            'description' => 'description',
            'amount' => 'amount_gross',
            'status' => 'status',
        ], 'date', 'desc');

        $expenses = $query->paginate(25)->withQueryString();

        $totalsQuery = Expense::query()
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        $totals = [
            'gross' => (float) (clone $totalsQuery)->sum('amount_gross'),
            'reimbursable' => (float) (clone $totalsQuery)
                ->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Reimbursed->value, ExpenseStatus::Invoiced->value])
                ->where('payment_method', PaymentMethod::PrivatePaid->value)
                ->sum('amount_gross'),
            'pending' => (int) (clone $totalsQuery)->where('status', ExpenseStatus::Pending->value)->count(),
            'reimbursement_pending' => (float) Expense::query()
                ->where('user_id', Auth::id())
                ->where('status', ExpenseStatus::Approved->value)
                ->where('payment_method', PaymentMethod::PrivatePaid->value)
                ->whereNull('reimbursed_at')
                ->sum('amount_gross'),
            'current_month' => (float) Expense::query()
                ->where('user_id', Auth::id())
                ->whereBetween('date', [
                    CarbonImmutable::today()->startOfMonth()->toDateString(),
                    CarbonImmutable::today()->endOfMonth()->toDateString(),
                ])
                ->sum('amount_gross'),
        ];

        return view('expenses.index', [
            'expenses' => $expenses,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'sort' => $sort,
            'dir' => $dir,
            'statusFilter' => $statusFilter,
            'statusOptions' => ExpenseStatus::cases(),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Expense::class);

        return view('expenses._form_dialog', [
            'expense' => null,
            'date' => $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString(),
        ] + $this->formData());
    }

    public function store(SaveExpenseRequest $request): RedirectResponse {
        Gate::authorize('create', Expense::class);

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        /** @var User $user */
        $user = Auth::user();
        $data['organization_id'] = $user->organization_id;

        $expense = $this->service->create($data);

        if ($request->boolean('submit_after_save')) {
            $this->service->submitForApproval($expense);

            return redirect()->route('expenses.index')
                ->with('success', __('Spese erfasst und zur Genehmigung eingereicht.'));
        }

        return redirect()->route('expenses.index')
            ->with('success', __('Spese als Entwurf gespeichert.'));
    }

    public function edit(Expense $expense): View {
        Gate::authorize('update', $expense);

        return view('expenses._form_dialog', [
            'expense' => $expense,
            'date' => $expense->date->toDateString(),
        ] + $this->formData());
    }

    public function update(SaveExpenseRequest $request, Expense $expense): RedirectResponse {
        Gate::authorize('update', $expense);

        $this->service->update($expense, $request->validated());

        if ($request->boolean('submit_after_save') && $expense->fresh()?->status === ExpenseStatus::Draft) {
            $this->service->submitForApproval($expense);

            return redirect()->route('expenses.index')
                ->with('success', __('Spese gespeichert und zur Genehmigung eingereicht.'));
        }

        return redirect()->route('expenses.index')
            ->with('success', __('Spese aktualisiert.'));
    }

    public function destroy(Expense $expense): RedirectResponse {
        Gate::authorize('delete', $expense);

        $this->service->delete($expense);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese gelöscht.'));
    }

    public function submit(Expense $expense): RedirectResponse {
        Gate::authorize('submit', $expense);

        $this->service->submitForApproval($expense);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese zur Genehmigung eingereicht.'));
    }

    public function cancel(Expense $expense): RedirectResponse {
        Gate::authorize('cancel', $expense);

        $this->service->cancel($expense);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese storniert.'));
    }

    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', Expense::class);

        [$from, $to] = $this->resolveRange($request);

        $filename = sprintf('expenses-%s_%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        $expenses = Expense::query()
            ->with([
                'user:id,name',
                'category:id,label',
                'project:id,name,customer_id',
                'customer:id,name',
            ])
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        return response()->streamDownload(function () use ($expenses): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // UTF-8 BOM für Excel-Kompatibilität
            fwrite($out, \CommonToolkit\Helper\Data\StringHelper::BOM_UTF8);
            fwrite($out, StringHelper::encodeLine([
                'Datum',
                'Mitarbeiter',
                'Kategorie',
                'Händler',
                'Projekt',
                'Kunde',
                'Brutto',
                'Netto',
                'Steuer',
                'Steuersatz %',
                'Währung',
                'Zahlungsweise',
                'Status',
                'Abrechenbar',
                'Genehmigt am',
                'Erstattet am',
                'Erstattungsreferenz',
                'Beschreibung',
            ], ';') . "\r\n");
            foreach ($expenses as $expense) {
                fwrite($out, StringHelper::encodeLine([
                    $expense->date->format('Y-m-d'),
                    $expense->user->name ?? '',
                    $expense->category->label ?? '',
                    (string) $expense->vendor,
                    $expense->project->name ?? '',
                    $expense->customer->name ?? '',
                    number_format((float) $expense->amount_gross, 2, ',', ''),
                    number_format((float) $expense->amount_net, 2, ',', ''),
                    number_format((float) $expense->tax_amount, 2, ',', ''),
                    number_format((float) $expense->tax_rate, 2, ',', ''),
                    (string) $expense->currency,
                    $expense->payment_method->label(),
                    $expense->status->label(),
                    $expense->billable ? 'ja' : 'nein',
                    $expense->decided_at?->format('Y-m-d'),
                    $expense->reimbursed_at?->format('Y-m-d'),
                    (string) $expense->reimbursement_reference,
                    (string) $expense->description,
                ], ';') . "\r\n");
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array {
        return [
            'categories' => ExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('label')
                ->get(['id', 'label', 'default_tax_rate', 'default_billable', 'icon', 'color']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::cases(),
        ];
    }
}
