<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Invoicing\InvoiceGenerator;
use App\Services\UI\DateRangeContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);
        $invoices = Invoice::query()
            ->with(['customer'])
            ->latest('id')
            ->paginate(25);
        $statuses = Invoice::STATUSES;

        return view('invoices.index', compact('invoices', 'statuses'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);
        $customers = Customer::query()->orderBy('name')->get();
        $projects = Project::query()->orderBy('name')->get();
        $globalRange = app(DateRangeContext::class)->current();
        $defaultFrom = $globalRange['from']->toDateString();
        $defaultTo = $globalRange['to']->toDateString();

        return view('invoices.create', compact('customers', 'projects', 'defaultFrom', 'defaultTo'));
    }

    public function store(Request $request, InvoiceGenerator $gen): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);
        /** @var Project|null $project */
        $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;

        $invoice = $gen->fromTimeEntries($customer, $project, [
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Rechnungsentwurf erstellt.'));
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        return view('invoices.show', compact('invoice'));
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', __('Rechnung gelöscht.'));
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('issue', $invoice);
        $invoice->update([
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => now(),
            'due_on' => now()->addDays(14),
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Rechnung gestellt.'));
    }

    public function pay(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('pay', $invoice);
        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'paid_on' => now(),
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Rechnung bezahlt.'));
    }

    public function pdf(Invoice $invoice): SymfonyResponse
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4')
            ->download('rechnung-'.$invoice->number.'.pdf');
    }
}
