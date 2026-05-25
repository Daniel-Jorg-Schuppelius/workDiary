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

use App\Http\Requests\SaveInvoiceItemRequest;
use App\Mail\InvoiceMail;
use App\Models\{Customer, Expense, Invoice, InvoiceItem, InvoiceMailTemplate, Project};
use App\Services\Expense\ExpenseInvoicingService;
use App\Services\Invoicing\InvoiceGenerator;
use App\Services\UI\DateRangeContext;
use App\Support\SortableQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Mail};
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Invoice::class);

        $customerId = $request->integer('customer') ?: null;
        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, Invoice::STATUSES, true) ? $status : '';

        $query = Invoice::query()->with(['customer'])
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter));

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'number' => 'number',
            'issued_on' => 'issued_on',
            'status' => 'status',
            'total' => 'total',
        ], 'issued_on', 'desc');

        $invoices = $query->paginate(25)->withQueryString();
        $statuses = Invoice::STATUSES;
        $customers = Customer::query()->orderBy('name')->get(['id', 'name']);
        $filters = ['customer' => $customerId, 'status' => $statusFilter];

        return view('invoices.index', compact('invoices', 'statuses', 'customers', 'filters', 'sort', 'dir'));
    }

    public function create(Request $request): View {
        Gate::authorize('create', Invoice::class);
        $customers = Customer::query()->orderBy('name')->get();
        $projects = Project::query()->orderBy('name')->get();
        $globalRange = app(DateRangeContext::class)->current();
        $defaultFrom = $globalRange['from']->toDateString();
        $defaultTo = $globalRange['to']->toDateString();

        return view('invoices._form_dialog', compact('customers', 'projects', 'defaultFrom', 'defaultTo'));
    }

    public function store(Request $request, InvoiceGenerator $gen): RedirectResponse {
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

    public function show(Invoice $invoice): View {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        return view('invoices.show', compact('invoice'));
    }

    public function destroy(Invoice $invoice): RedirectResponse {
        Gate::authorize('delete', $invoice);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', __('Rechnung gelöscht.'));
    }

    public function issue(Invoice $invoice): RedirectResponse {
        Gate::authorize('issue', $invoice);
        $invoice->update([
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => now(),
            'due_on' => now()->addDays(14),
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Rechnung gestellt.'));
    }

    public function pay(Invoice $invoice): RedirectResponse {
        Gate::authorize('pay', $invoice);
        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'paid_on' => now(),
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Rechnung bezahlt.'));
    }

    public function pdf(Invoice $invoice): SymfonyResponse {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        $template = $invoice->customer?->invoice_template_id
            ? \App\Models\InvoiceTemplate::find($invoice->customer->invoice_template_id)
            : \App\Models\InvoiceTemplate::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('is_default', true)
            ->first();

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice, 'template' => $template])
            ->setPaper('a4')
            ->download('rechnung-' . $invoice->number . '.pdf');
    }

    public function itemForm(Invoice $invoice, ?InvoiceItem $item = null): View {
        Gate::authorize('update', $invoice);
        $item ??= new InvoiceItem();

        return view('invoices._item_form_dialog', [
            'invoice' => $invoice,
            'item' => $item,
        ]);
    }

    public function addItem(SaveInvoiceItemRequest $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);
        $data = $request->validated();

        $invoice->items()->create([
            'organization_id' => $invoice->organization_id,
            'description' => $data['description'],
            'quantity' => (string) $data['quantity'],
            'unit' => $data['unit'] ?? (string) __('invoicing.unit_hour'),
            'unit_price' => (string) $data['unit_price'],
            'position' => $data['position'] ?? ((int) $invoice->items()->max('position') + 1),
        ]);

        $this->refreshTotals($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Position hinzugefügt.'));
    }

    public function updateItem(SaveInvoiceItemRequest $request, Invoice $invoice, InvoiceItem $item): RedirectResponse {
        Gate::authorize('update', $invoice);
        abort_unless($item->invoice_id === $invoice->id, 404);
        $data = $request->validated();

        $item->update([
            'description' => $data['description'],
            'quantity' => (string) $data['quantity'],
            'unit' => $data['unit'] ?? $item->unit,
            'unit_price' => (string) $data['unit_price'],
            'position' => $data['position'] ?? $item->position,
        ]);

        $this->refreshTotals($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Position aktualisiert.'));
    }

    public function removeItem(Invoice $invoice, InvoiceItem $item): RedirectResponse {
        Gate::authorize('update', $invoice);
        abort_unless($item->invoice_id === $invoice->id, 404);

        $item->delete();
        $this->refreshTotals($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Position entfernt.'));
    }

    private function refreshTotals(Invoice $invoice): void {
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();
    }

    public function expensesForm(Invoice $invoice, ExpenseInvoicingService $service): View {
        Gate::authorize('update', $invoice);
        $expenses = $service->availableForInvoice($invoice)->get();

        return view('invoices._attach_expenses_dialog', [
            'invoice' => $invoice,
            'expenses' => $expenses,
        ]);
    }

    public function attachExpenses(Request $request, Invoice $invoice, ExpenseInvoicingService $service): RedirectResponse {
        Gate::authorize('update', $invoice);

        $data = $request->validate([
            'expense_ids' => ['required', 'array', 'min:1'],
            'expense_ids.*' => ['integer', 'exists:expenses,id'],
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Expense> $expenses */
        $expenses = Expense::query()->whereIn('id', $data['expense_ids'])->get();
        $service->addToInvoice($invoice, $expenses);

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __(':count Spese(n) hinzugefügt.', ['count' => $expenses->count()]));
    }

    /**
     * Direktes Storno (nur draft/issued). Bezahlte Rechnungen müssen über
     * eine Korrekturrechnung storniert werden — siehe {@see creditNote()}.
     */
    public function cancel(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('cancel', $invoice);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice->cancel($data['reason'] ?? null, (int) Auth::id());

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('Rechnung storniert.'));
    }

    /**
     * Erzeugt eine Gutschrift (Korrekturrechnung) zu einer bezahlten Rechnung.
     * Leitet auf die neue Korrekturrechnung weiter.
     */
    public function creditNote(Invoice $invoice, InvoiceGenerator $gen): RedirectResponse {
        Gate::authorize('createCreditNote', $invoice);
        $credit = $gen->creditNoteFor($invoice, (int) Auth::id());

        return redirect()->route('invoices.show', $credit)
            ->with('status', __('Korrekturrechnung :nr erstellt.', ['nr' => $credit->number]));
    }

    /**
     * Zeigt den Versand-Dialog mit Empfängern, Template-Auswahl und Freitext.
     */
    public function sendForm(Invoice $invoice): View {
        Gate::authorize('send', $invoice);
        $invoice->load(['customer', 'items']);

        $templates = InvoiceMailTemplate::query()
            ->where(function ($q) use ($invoice): void {
                $q->where('organization_id', $invoice->organization_id)
                    ->orWhereNull('organization_id');
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $defaultTemplate = InvoiceMailTemplate::defaultFor($invoice->organization_id);
        $defaultTo = $invoice->customer->primaryContact()['email']
            ?? $invoice->customer->email
            ?? '';

        return view('invoices._send_dialog', [
            'invoice' => $invoice,
            'templates' => $templates,
            'defaultTemplateId' => $defaultTemplate->id,
            'defaultTo' => $defaultTo,
            'variables' => InvoiceMailTemplate::availableVariables(),
        ]);
    }

    /**
     * Versendet die Rechnung. Multi-Empfänger (To/CC), automatisches BCC an
     * Absender, optional Statusübergang draft→issued, Queue, PDF-Anhang.
     */
    public function send(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('send', $invoice);

        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:invoice_mail_templates,id'],
            'to' => ['required', 'array', 'min:1', 'max:20'],
            'to.*' => ['required', 'email:rfc'],
            'cc' => ['nullable', 'array', 'max:20'],
            'cc.*' => ['email:rfc'],
            'bcc' => ['nullable', 'array', 'max:20'],
            'bcc.*' => ['email:rfc'],
            'custom_text' => ['nullable', 'string', 'max:5000'],
            'bcc_sender' => ['nullable', 'boolean'],
        ]);

        /** @var InvoiceMailTemplate $template */
        $template = InvoiceMailTemplate::query()->findOrFail($data['template_id']);
        // Org-Sicherheit: globale Templates oder Templates der eigenen Org
        if ($template->organization_id !== null && $template->organization_id !== $invoice->organization_id) {
            abort(403);
        }

        $rendered = $template->renderForInvoice($invoice, $data['custom_text'] ?? null);

        $bcc = $data['bcc'] ?? [];
        if (! empty($data['bcc_sender'])) {
            $senderAddr = (string) config('mail.from.address');
            if ($senderAddr !== '' && ! in_array($senderAddr, $bcc, true)) {
                $bcc[] = $senderAddr;
            }
        }

        $mail = new InvoiceMail($invoice, $rendered['subject'], $rendered['html'], $rendered['text']);
        $pending = Mail::to($data['to']);
        if (! empty($data['cc'])) {
            $pending->cc($data['cc']);
        }
        if (! empty($bcc)) {
            $pending->bcc($bcc);
        }
        $pending->queue($mail);

        $invoice->markSent();

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('Rechnung an :count Empfänger versendet.', [
                'count' => count($data['to']),
            ]));
    }
}
