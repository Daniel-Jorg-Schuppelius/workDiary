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
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate, Mail};
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
        $customerId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer::class, $rawCustomerId);

        $rawProjectId = $request->input('project_id');
        $projectId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Project::class, $rawProjectId);

        $rawForeignId = $request->input('foreign_customer_id');
        $foreignCustomerId = \App\Support\Sqid::decodeOrNumeric(\App\Models\ForeignCustomer::class, $rawForeignId);

        $request->merge([
            'customer_id' => $customerId,
            'project_id' => $projectId,
            'foreign_customer_id' => $foreignCustomerId,
        ]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'foreign_customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('foreign_customers')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'content' => ['nullable', 'in:service,material,proforma,down_payment'],
            'mark_partial' => ['nullable', 'boolean'],
            'dp_description' => ['required_if:content,down_payment', 'nullable', 'string', 'max:500'],
            'dp_amount' => ['required_if:content,down_payment', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'dp_service_date' => ['nullable', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);
        /** @var Project|null $project */
        $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;
        /** @var \App\Models\ForeignCustomer|null $foreignCustomer */
        $foreignCustomer = isset($data['foreign_customer_id']) ? \App\Models\ForeignCustomer::query()->find($data['foreign_customer_id']) : null;

        // Hoheits-Sperre (Feature 045): führt ein externes Programm (Lexoffice/DATEV)
        // die Fakturierung des Kunden, ist die lokale Rechnungserstellung gesperrt.
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

        // Pro-forma (MVP-171): eigener Nummernkreis, keine Quellposten (Positionen manuell).
        if (($data['content'] ?? 'service') === 'proforma') {
            $invoice = $gen->emptyProforma($customer, $project);
        } elseif (($data['content'] ?? 'service') === 'down_payment') {
            // Abschlags-/Anzahlungsrechnung (Belegkette 066): Teilentgelt vor Leistung, Anrechnung in der Schlussrechnung.
            $invoice = $gen->downPaymentFor(
                $customer,
                $project,
                (string) $data['dp_description'],
                (string) $data['dp_amount'],
                isset($data['dp_service_date']) ? \Illuminate\Support\Carbon::parse($data['dp_service_date']) : null,
            );
        } elseif (($data['content'] ?? 'service') === 'material') {
            // Material wird getrennt abgerechnet (eigene Rechnung mit Lieferdatum).
            $invoice = $gen->fromMaterialUsages($customer, $project, $range, $foreignCustomer);
        } else {
            $invoice = $gen->fromTimeEntries($customer, $project, $range, $foreignCustomer);
        }

        // Teilrechnung (Belegkette 066): reine Kennzeichnung des Entwurfs, keine Anrechnungslogik.
        if (! empty($data['mark_partial']) && $invoice->type === Invoice::TYPE_INVOICE) {
            $invoice->update(['type' => Invoice::TYPE_PARTIAL]);
        }

        // Zahlungsziel je Rechnung (MVP-163): steuert due_on bei issue()/markSent().
        if (isset($data['payment_terms_days'])) {
            $invoice->update(['payment_terms_days' => (int) $data['payment_terms_days']]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', match ($data['content'] ?? 'service') {
            'proforma' => __('Pro-forma-Entwurf erstellt.'),
            'down_payment' => __('Abschlagsrechnungs-Entwurf erstellt.'),
            'material' => __('Materialrechnungs-Entwurf erstellt.'),
            default => __('Rechnungsentwurf erstellt.'),
        });
    }

    public function show(Invoice $invoice): View {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        // Belegkette 066: anrechenbare offene Abschläge für den Schlussrechnungs-CTA.
        $openDownPaymentCount = 0;
        if ($invoice->status === Invoice::STATUS_DRAFT && $invoice->type === Invoice::TYPE_INVOICE) {
            $openDownPaymentCount = app(InvoiceGenerator::class)
                ->openDownPaymentsFor($invoice->customer, $invoice->project_id, $invoice->currency->value)
                ->count();
        }
        $settledByInvoice = $invoice->settledByInvoice();

        return view('invoices.show', compact('invoice', 'openDownPaymentCount', 'settledByInvoice'));
    }

    /**
     * Belegkette 066: Entwurf zur Schlussrechnung machen — rechnet alle offenen
     * Abschlagsrechnungen desselben Kontexts an (§ 14 Abs. 5 UStG).
     */
    public function makeFinal(Invoice $invoice, InvoiceGenerator $gen): RedirectResponse {
        Gate::authorize('update', $invoice);

        if ($invoice->type !== Invoice::TYPE_INVOICE) {
            return back()->with('error', __('Nur ein Standard-Rechnungsentwurf kann zur Schlussrechnung werden.'));
        }

        try {
            $gen->finalFromDraft($invoice);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('Entwurf in Schlussrechnung umgewandelt — offene Abschlagsrechnungen wurden angerechnet.'));
    }

    public function destroy(Invoice $invoice): RedirectResponse {
        Gate::authorize('delete', $invoice);
        // Transaktion: Positions-Löschung + Quellposten-Freigabe (Hooks in
        // Invoice/InvoiceItem) sollen atomar mit dem Rechnungs-Delete laufen.
        DB::transaction(fn() => $invoice->delete());

        return redirect()->route('invoices.index')->with('status', __('Rechnung gelöscht.'));
    }

    /** Validierungsbericht (MVP-164): Preflight + XSD + KoSIT VOR Ausstellung. */
    public function einvoiceValidation(Invoice $invoice): \Illuminate\View\View {
        Gate::authorize('view', $invoice);

        return view('invoices.einvoice-validation', [
            'invoice' => $invoice,
            'report' => app(\App\Services\Invoicing\EInvoice\EInvoiceValidationService::class)->validate($invoice),
        ]);
    }

    /** Prüfung/Freigabe (MVP-163): optionaler Schritt vor der Ausstellung. */
    public function approve(Invoice $invoice): RedirectResponse {
        Gate::authorize('issue', $invoice);
        abort_unless($invoice->status === Invoice::STATUS_DRAFT, 422);

        $invoice->update(['approved_at' => now(), 'approved_by' => (int) Auth::id()]);
        $invoice->audit('invoice.approved', ['by' => (int) Auth::id()]);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Rechnung freigegeben.'));
    }

    /** Mahn-Dialog (MVP-163, UI-Nacharbeit): Stufe + optionaler Mailversand. */
    public function dunForm(Invoice $invoice): View {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        abort_unless($invoice->isOverdue() && (int) $invoice->dunning_level < 3, 422);
        $invoice->load('customer');

        return view('invoices._dun_dialog', [
            'invoice' => $invoice,
            'nextLevel' => (int) $invoice->dunning_level + 1,
            'defaultTo' => $invoice->customer->primaryContact()['email'] ?? $invoice->customer->email ?? '',
        ]);
    }

    /** Mahnstufe erhöhen (MVP-163): nur für überfällige Rechnungen, max. 3. */
    public function dun(Request $request, Invoice $invoice): RedirectResponse {
        // Mahnen betrifft AUSGESTELLTE Rechnungen — die issue-Policy (nur
        // draft) passt nicht; Maßstab ist das Abrechnungsrecht.
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        if (! $invoice->isOverdue()) {
            return back()->with('error', __('Nur überfällige Rechnungen können gemahnt werden.'));
        }
        if ((int) $invoice->dunning_level >= 3) {
            return back()->with('error', __('Höchste Mahnstufe bereits erreicht.'));
        }

        $data = $request->validate([
            'send_mail' => ['nullable', 'boolean'],
            'email' => ['nullable', 'required_if_accepted:send_mail', 'email:rfc'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $newLevel = (int) $invoice->dunning_level + 1;
        $invoice->update(['dunning_level' => $newLevel, 'dunned_at' => now()]);
        $invoice->audit('invoice.dunned', ['level' => $newLevel, 'mailed' => ! empty($data['send_mail'])]);

        // Mahn-Mailversand (MVP-163, Restpaket): eigener Zustellversuch —
        // die Rechnung selbst bleibt unverändert (kein neuer Beleg).
        if (! empty($data['send_mail'])) {
            $mail = new \App\Mail\DunningMail($invoice, $newLevel, $data['note'] ?? null);
            Mail::to((string) $data['email'])->queue($mail);
            $this->recordDispatch($invoice, \App\Models\InvoiceDispatch::CHANNEL_EMAIL, 'pdf', (string) $data['email'], null, [
                'kind' => 'dunning',
                'level' => $newLevel,
            ]);

            return redirect()->route('invoices.show', $invoice)
                ->with('status', __('Mahnstufe :level vermerkt und Mahnung an :email versendet.', ['level' => $newLevel, 'email' => $data['email']]));
        }

        return redirect()->route('invoices.show', $invoice)->with('status', __('Mahnstufe :level vermerkt.', ['level' => $newLevel]));
    }

    /**
     * Zustellversuch protokollieren (MVP-168): Kanal/Format/Empfänger/Hash.
     *
     * @param  array<string, mixed>  $meta
     */
    private function recordDispatch(Invoice $invoice, string $channel, ?string $format, ?string $recipient, ?string $sha256, array $meta = []): void {
        \App\Models\InvoiceDispatch::query()->create([
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'channel' => $channel,
            'format' => $format,
            'status' => $channel === \App\Models\InvoiceDispatch::CHANNEL_EMAIL ? 'queued' : 'sent',
            'recipient' => $recipient,
            'sha256' => $sha256,
            'meta' => $meta !== [] ? $meta : null,
            'created_by' => Auth::id(),
        ]);
    }

    public function issue(Invoice $invoice): RedirectResponse {
        Gate::authorize('issue', $invoice);

        // Pro-forma ist keine steuerliche Rechnung (MVP-171): kein
        // Rechnungsstatus — Umwandlung läuft über proformaConvert().
        if ($invoice->isProforma()) {
            return back()->with('error', __('Eine Pro-forma-Rechnung wird nicht gestellt — wandeln Sie sie in eine echte Rechnung um.'));
        }

        // MVP-163 (Opt-in): Prüfung/Freigabe vor Ausstellung erzwingen.
        $invoicingSettings = (array) data_get($invoice->organization?->settings, 'invoicing', []);
        if ((string) ($invoicingSettings['require_approval'] ?? '0') === '1' && $invoice->approved_at === null) {
            return back()->with('error', __('Die Rechnung braucht vor der Ausstellung eine Freigabe.'));
        }

        // MVP-164 (Opt-in): erzwungene E-Rechnungs-Validierung vor der
        // Ausstellung — Fehler blocken; ohne das Setting bleibt das
        // Bestandsverhalten (Bericht jederzeit manuell abrufbar).
        $einvoiceSettings = (array) data_get($invoice->organization?->settings, 'einvoice', []);
        if ((string) ($einvoiceSettings['enforce_validation'] ?? '0') === '1') {
            $report = app(\App\Services\Invoicing\EInvoice\EInvoiceValidationService::class)->validate($invoice);
            if (! $report['valid'] || $report['preflight_errors'] !== []) {
                return redirect()->route('invoices.einvoice-validation', $invoice)
                    ->with('error', __('Die Rechnung besteht die E-Rechnungs-Validierung nicht — Ausstellung abgebrochen.'));
            }
        }

        // MVP-162: Zahlungsziel je Rechnung + Partei-Snapshot einfrieren —
        // ab jetzt ist der Beleg fachlich unveränderlich (Model-Guard).
        // Phase 23 (MVP-243): der tatsächlich verwendete Steuerkontext
        // (Regelquelle, Stichtag, Kategorie, Aufriss) friert MIT ein.
        $invoice->loadMissing(['items', 'customer', 'organization']);
        $organization = $invoice->organization;
        $taxResolution = $organization !== null
            ? app(\App\Services\Invoicing\TaxResolver::class)->resolve($organization, $invoice->customer, $invoice->serviceDateTo() ?? now())
            : null;
        $invoice->freezeParties();
        $invoice->update([
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => now(),
            'due_on' => now()->addDays($invoice->payment_terms_days ?? 14),
            'tax_context' => [
                'resolved_on' => ($invoice->serviceDateTo() ?? now())->toDateString(),
                'rate' => (string) $invoice->tax_rate,
                'is_reverse_charge' => (bool) $invoice->is_reverse_charge,
                'breakdown' => $invoice->tax_breakdown,
                'category' => $taxResolution['category'] ?? null,
                'rule' => $taxResolution['rule'] ?? null,
                'item_categories' => $invoice->items->pluck('tax_category', 'id')->all(),
            ],
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

        $bytes = app(\App\Services\Invoicing\InvoicePdfRenderer::class)->output($invoice);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rechnung-' . $invoice->number . '.pdf"',
        ]);
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

        // Pro-forma ist keine steuerliche Rechnung — nie als XRechnung (MVP-171).
        abort_if($invoice->isProforma(), 404);

        $billingMode = app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer);
        abort_if($billingMode->isExternal(), 404);

        $result = $generator->preflight($invoice);
        if ($result['errors'] !== []) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', __('invoicing.einvoice.error_intro') . ' ' . implode(' ', $result['errors']));
        }

        $xml = $generator->generate($invoice);
        $filename = 'XRechnung_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->number) . '.xml';

        // Übergabenachweis (MVP-168): Format + Dateihash revisionsfest im Audit.
        $invoice->audit('invoice.einvoice_exported', [
            'format' => 'xrechnung_ubl',
            'filename' => $filename,
            'sha256' => hash('sha256', $xml),
        ]);
        $this->recordDispatch($invoice, \App\Models\InvoiceDispatch::CHANNEL_DOWNLOAD, 'xrechnung_ubl', null, hash('sha256', $xml), ['filename' => $filename]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * ZUGFeRD-Download (Feature 045, Abschnitt 8): PDF/A-3 (EN 16931) mit
     * eingebettetem CII-XML zur lokalen Ausgangsrechnung. Gleiche Gates und
     * Hoheits-Sperre wie {@see einvoiceDownload()}; die visuelle Darstellung
     * ist die bestehende Rechnungs-PDF-View (`invoices.pdf`). BT-10
     * (BuyerReference) ist hier — anders als bei der XRechnung — keine
     * Pflicht (Preflight mit Profil EN 16931).
     */
    public function zugferdDownload(Invoice $invoice, \App\Services\Invoicing\EInvoice\XRechnungGenerator $generator): SymfonyResponse {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        // Pro-forma ist keine steuerliche Rechnung — nie als ZUGFeRD (MVP-171).
        abort_if($invoice->isProforma(), 404);

        $billingMode = app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer);
        abort_if($billingMode->isExternal(), 404);

        abort_unless($generator->zugferdAvailable(), 503, (string) __('invoicing.einvoice.zugferd.unavailable'));

        $result = $generator->preflight($invoice, \ERechnungToolkit\Enums\ERechnungProfile::EN16931);
        if ($result['errors'] !== []) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', __('invoicing.einvoice.zugferd.error_intro') . ' ' . implode(' ', $result['errors']));
        }

        // Feature 076: dieselbe komponierte Darstellung (Firmenbogen/Design)
        // wie der direkte PDF-Download — vor der XML-Einbettung (MVP-301).
        $visualHtml = app(\App\Services\Invoicing\InvoicePdfRenderer::class)->composedHtml($invoice);
        $pdf = $generator->generateZugferdPdf($invoice, $visualHtml);

        if ($pdf === null) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', __('invoicing.einvoice.zugferd.failed'));
        }

        $filename = 'ZUGFeRD_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->number) . '.pdf';

        // Übergabenachweis (MVP-168, Restpaket): analog zum XRechnung-Pfad.
        $invoice->audit('invoice.einvoice_exported', [
            'format' => 'zugferd_pdf',
            'filename' => $filename,
            'sha256' => hash('sha256', $pdf),
        ]);
        $this->recordDispatch($invoice, \App\Models\InvoiceDispatch::CHANNEL_DOWNLOAD, 'zugferd_pdf', null, hash('sha256', $pdf), ['filename' => $filename]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
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
            'discount_percent' => $data['discount_percent'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
            'tax_rate' => $data['tax_rate'] ?? null,
            'tax_category' => $data['tax_category'] ?? null,
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
            'discount_percent' => $data['discount_percent'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
            'tax_rate' => array_key_exists('tax_rate', $data) ? $data['tax_rate'] : $item->tax_rate,
            'tax_category' => array_key_exists('tax_category', $data) ? $data['tax_category'] : $item->tax_category,
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

    /** MVP-416: Rabatt-/Skonto-Konditionen des Entwurfs (Dialog). */
    public function conditionsForm(Invoice $invoice): View {
        Gate::authorize('update', $invoice);

        return view('invoices._conditions_dialog', ['invoice' => $invoice]);
    }

    /** MVP-416: Belegrabatt (Prozent XOR Betrag) + Skonto-Kondition — nur vor Ausstellung. */
    public function updateConditions(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);
        abort_if($invoice->party_snapshot !== null, 403, (string) __('Ausgestellte Belege sind unveränderlich.'));

        $data = $request->validate([
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'prohibits:discount_amount'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'skonto_percent' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_with:skonto_days'],
            'skonto_days' => ['nullable', 'integer', 'min:1', 'max:365', 'required_with:skonto_percent'],
        ]);

        $invoice->fill([
            'discount_percent' => $data['discount_percent'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? null,
            'skonto_percent' => $data['skonto_percent'] ?? null,
            'skonto_days' => $data['skonto_days'] ?? null,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return redirect()->route('invoices.show', $invoice)->with('status', __('Konditionen gespeichert.'));
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
            'expense_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('expenses')],
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
            'template_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('invoice_mail_templates')],
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

        // Zustellnachweis (MVP-168): jeder Versand ist ein eigener Versuch.
        $this->recordDispatch($invoice, \App\Models\InvoiceDispatch::CHANNEL_EMAIL, 'pdf', implode(', ', $data['to']), null, [
            'cc' => $data['cc'] ?? [],
            'template_id' => $template->id,
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('Rechnung an :count Empfänger versendet.', [
                'count' => count($data['to']),
            ]));
    }

    /**
     * Pro-forma → echte Rechnung (MVP-171): neue Nummer aus dem
     * Rechnungskreis, voller Ausstellungs-Weg danach; die Pro-forma
     * bleibt unverändert verknüpft.
     */
    public function proformaConvert(Invoice $invoice, \App\Services\Invoicing\QuoteService $quotes): RedirectResponse {
        Gate::authorize('create', Invoice::class);
        abort_unless($invoice->isProforma(), 404);

        /** @var \App\Models\User $actor */
        $actor = Auth::user();
        $real = $quotes->proformaToInvoice($invoice, $actor);

        return redirect()->route('invoices.show', $real)
            ->with('status', __('Rechnung :nr aus Pro-forma :proforma erstellt.', ['nr' => $real->number, 'proforma' => $invoice->number]));
    }
}
