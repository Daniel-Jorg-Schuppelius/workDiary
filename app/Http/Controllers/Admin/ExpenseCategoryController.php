<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', ExpenseCategory::class);

        $query = ExpenseCategory::query()->withCount('expenses');

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'sort' => 'sort',
            'label' => 'label',
            'slug' => 'slug',
            'tax' => 'default_tax_rate',
            'is_active' => 'is_active',
            'expenses' => 'expenses_count',
        ], 'sort', 'asc');

        $categories = $query->paginate((int) setting('pagination.expense_categories', 25))->withQueryString();

        return view('admin.expense-categories.index', compact('categories', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', ExpenseCategory::class);

        return view('admin.expense-categories._form_dialog', [
            'category' => new ExpenseCategory,
        ]);
    }

    public function store(SaveExpenseCategoryRequest $request): RedirectResponse {
        Gate::authorize('create', ExpenseCategory::class);

        ExpenseCategory::create($request->validated());

        return redirect()->route('admin.expense-categories.index')
            ->with('success', __('Spesenkategorie wurde angelegt.'));
    }

    public function edit(ExpenseCategory $expenseCategory): View {
        Gate::authorize('update', $expenseCategory);

        return view('admin.expense-categories._form_dialog', [
            'category' => $expenseCategory,
        ]);
    }

    public function update(SaveExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse {
        Gate::authorize('update', $expenseCategory);

        $expenseCategory->update($request->validated());

        return redirect()->route('admin.expense-categories.index')
            ->with('success', __('Spesenkategorie wurde aktualisiert.'));
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse {
        Gate::authorize('delete', $expenseCategory);

        if ($expenseCategory->expenses()->exists()) {
            return redirect()->route('admin.expense-categories.index')
                ->with('error', __('Kategorie wird noch von Spesen verwendet und kann nicht gelöscht werden.'));
        }

        $expenseCategory->delete();

        return redirect()->route('admin.expense-categories.index')
            ->with('success', __('Spesenkategorie wurde gelöscht.'));
    }
}
