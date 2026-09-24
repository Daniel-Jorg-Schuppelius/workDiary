<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceExpenseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\Invoice;
use App\Models\Travel\Expense;
use App\Services\Expense\ExpenseInvoicingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Spesen an eine Rechnung hängen (Reisekostenmodul); Routen `invoices.expenses.*`. */
class InvoiceExpenseController extends Controller {
    public function __construct(private readonly ExpenseInvoicingService $service) {}

    public function form(Invoice $invoice): View {
        Gate::authorize('update', $invoice);
        $expenses = $this->service->availableForInvoice($invoice)->get();

        return view('invoices._attach_expenses_dialog', [
            'invoice' => $invoice,
            'expenses' => $expenses,
        ]);
    }

    public function attach(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);

        $data = $request->validate([
            'expense_ids' => ['required', 'array', 'min:1'],
            'expense_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('expenses')],
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Expense> $expenses */
        $expenses = Expense::query()->whereIn('id', $data['expense_ids'])->get();
        $this->service->addToInvoice($invoice, $expenses);

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __(':count Spese(n) hinzugefügt.', ['count' => $expenses->count()]));
    }
}
