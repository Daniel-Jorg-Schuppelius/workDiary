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
use App\Models\{Customer, Expense, ExternalReference, Invoice, InvoiceItem, InvoiceMailTemplate, Project};
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

        $rawCustomer = (string) $request->query('customer', '');
        $customerId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer::class, $rawCustomer);
        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, Invoice::STATUSES, true) ? $status : '';

        $query = Invoice::query()->with(['customer'])
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter));

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
        $projects = Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']);
        $foreignCustomers = \App\Models\ForeignCustomer::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'company', 'customer_id']);
        $globalRange = app(DateRangeContext::class)->current();
        $defaultFrom = $globalRange['from']->toDateString();
        $defaultTo = $globalRange['to']->toDateString();

        return view('invoices._form_dialog', compact('customers', 'projects', 'foreignCustomers', 'defaultFrom', 'defaultTo'));
    }

    public function store(Request $request, InvoiceGenerator $gen): RedirectResponse {
        Gate::authorize('create', Invoice::class);

        $rawCustomerId = $request->input('customer_id');
        $customerId = \App\Support\Sqid::decode(\App\Models\Customer::class, $rawCustomerId);
        if ($customerId === null && is_numeric($rawCustomerId)) {
            $customerId = (int) $rawCustomerId;
        }

        $rawProjectId = $request->input('project_id');
        $projectId = \App\Support\Sqid::decode(\App\Models\Project::class, $rawProjectId);
        if ($projectId === null && is_numeric($rawProjectId)) {
            $projectId = (int) $rawProjectId;
        }

        $rawForeignId = $request->input('foreign_customer_id');
        $foreignCustomerId = \App\Support\Sqid::decode(\App\Models\ForeignCustomer::class, $rawForeignId);
        if ($foreignCustomerId === null && is_numeric($rawForeignId)) {
            $foreignCustomerId = (int) $rawForeignId;
        }

        $request->merge([
            'customer_id' => $customerId,
            'project_id' => $projectId,
            'foreign_customer_id' => $foreignCustomerId,
        ]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'foreign_customer_id' => ['nullable', 'integer', 'exists:foreign_customers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'content' => ['nullable', 'in:service,material'],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);
        /** @var Project|null $project */
        $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;
        /** @var \App\Models\ForeignCustomer|null $foreignCustomer */
        $foreignCustomer = isset($data['foreign_customer_id']) ? \App\Models\ForeignCustomer::query()->find($data['foreign_customer_id']) : null;

        // Hoheits-Sperre (Feature 045, additiv): führt ein externes Programm
        // (Lexoffice/DATEV) die Fakturierung dieses Kunden, ist die lokale
        // Rechnungserstellung gesperrt — Quellen gehen per Übergabenachweis.
        $billingMode = app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($customer);
        if ($billingMode->isExternal()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_id' => (string) __('finance.error.local_invoicing_locked', ['program' => $billingMode->label()]),
            ]);
        }

        $range = [
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ];

        // Material wird getrennt abgerechnet (eigene Rechnung mit Lieferdatum).
        if (($data['content'] ?? 'service') === 'material') {
            $invoice = $gen->fromMaterialUsages($customer, $project, $range, $foreignCustomer);

            return redirect()->route('invoices.show', $invoice)->with('status', __('Materialrechnungs-Entwurf erstellt.'));
        }

        $invoice = $gen->fromTimeEntries($customer, $project, $range, $foreignCustomer);

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

        // Hook: Wenn ein Plugin den PDF-Render übernehmen will (z. B. Lexoffice
        // liefert das offizielle PDF einer gepushten Rechnung), redirected
        // dieser Hook auf die Plugin-Route. So bleibt der Core entkoppelt.
        $hooked = ExternalReference::query()
            ->where('external_type', 'invoice')
            ->where('referenceable_type', $invoice->getMorphClass())
            ->where('referenceable_id', $invoice->getKey())
            ->first();

        if ($hooked !== null) {
            $pluginRoute = 'invoices.' . $hooked->plugin_id . '.pdf';
            if (\Illuminate\Support\Facades\Route::has($pluginRoute)) {
                return redirect()->route($pluginRoute, $invoice);
            }
        }

        $template = $invoice->customer->invoice_template_id
            ? \App\Models\InvoiceTemplate::find($invoice->customer->invoice_template_id)
            : \App\Models\InvoiceTemplate::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('is_default', true)
            ->first();

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'template' => $template,
            'orgLegal' => app(\App\Services\BrandingService::class)->legal(),
        ])
            ->setPaper('a4')
            ->download('rechnung-' . $invoice->number . '.pdf');
    }

    /**
     * E-Rechnung (Feature 045, Abschnitt 8): XRechnung-XML (UBL 2.1) zur
     * lokalen Ausgangsrechnung. Nur im Pfad „WorkDiary führt" — bei externer
     * Fakturierungshoheit (Lexoffice/DATEV) liegt die E-Rechnungs-Pflicht
     * beim führenden Programm (⇒ 404). Preflight-Fehler führen zurück zur
     * Detailansicht; Warnungen blockieren den Download nicht.
     */
    public function einvoiceDownload(Invoice $invoice, \App\Services\Invoicing\EInvoice\XRechnungGenerator $generator): SymfonyResponse {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer']);

        $billingMode = app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer);
        abort_if($billingMode->isExternal(), 404);

        $result = $generator->preflight($invoice);
        if ($result['errors'] !== []) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', __('invoicing.einvoice.error_intro') . ' ' . implode(' ', $result['errors']));
        }

        $xml = $generator->generate($invoice);
        $filename = 'XRechnung_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->number) . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
            'service_date' => $data['service_date'] ?? null,
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
            'service_date' => $data['service_date'] ?? null,
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
