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

use App\Enums\Invoicing\InvoiceDeliveryFormat;
use App\Http\Requests\SaveInvoiceItemRequest;
use App\Mail\InvoiceMail;
use App\Models\{Customer, Expense, ExternalReference, Invoice, InvoiceItem, InvoiceMailTemplate, Project};
use App\Services\Expense\ExpenseInvoicingService;
use App\Services\Invoicing\InvoiceGenerator;
use App\Services\Invoicing\{InvoiceIssueException, InvoiceIssueService};
use App\Services\UI\DateRangeContext;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate, Mail};
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller {
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

    /**
     * Read-only-Vorschau des Rechnungslaufs (MVP-462): rendert das Partial für
     * den Erstell-Dialog — Blöcke, Summen, Warnungen und Einzel-Einträge mit
     * Ausschluss-Checkboxen. Verbraucht nichts (keine Sperre, keine Nummer).
     */
    public function preview(Request $request, InvoiceGenerator $gen): View {
        Gate::authorize('create', Invoice::class);

        $request->merge([
            'customer_id' => \App\Support\Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
            'project_id' => \App\Support\Sqid::decodeOrNumeric(Project::class, $request->input('project_id')),
            'foreign_customer_id' => \App\Support\Sqid::decodeOrNumeric(\App\Models\ForeignCustomer::class, $request->input('foreign_customer_id')),
        ]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'foreign_customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('foreign_customers')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);
        /** @var Project|null $project */
        $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;
        /** @var \App\Models\ForeignCustomer|null $foreignCustomer */
        $foreignCustomer = isset($data['foreign_customer_id']) ? \App\Models\ForeignCustomer::query()->find($data['foreign_customer_id']) : null;

        try {
            $preview = $gen->previewTimeEntries($customer, $project, [
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
            ], $foreignCustomer);
        } catch (\App\Services\Finance\BillingModeLockedException $e) {
            return view('invoices._preview', ['preview' => null, 'blocked' => $e->getMessage()]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return view('invoices._preview', ['preview' => null, 'blocked' => collect($e->errors())->flatten()->first()]);
        }

        return view('invoices._preview', ['preview' => $preview, 'blocked' => null]);
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
            'delivery_format' => ['nullable', \Illuminate\Validation\Rule::enum(InvoiceDeliveryFormat::class)],
            'buyer_reference' => ['nullable', 'string', 'max:100'],
            'excluded_time_entry_ids' => ['nullable', 'array', 'max:500'],
            'excluded_time_entry_ids.*' => ['string'],
        ]);

        // In der Vorschau abgewählte Einträge (MVP-462): Sqids dekodieren;
        // fremde/ungültige IDs sind harmlos, weil whereNotIn nur ausschließt.
        $excludedEntryIds = [];
        foreach ((array) ($data['excluded_time_entry_ids'] ?? []) as $sqid) {
            $decoded = \App\Support\Sqid::decodeOrNumeric(\App\Models\TimeEntry::class, $sqid);
            if ($decoded !== null) {
                $excludedEntryIds[] = $decoded;
            }
        }

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
            $invoice = $gen->fromTimeEntries($customer, $project, $range, $foreignCustomer, $excludedEntryIds);
        }

        // Teilrechnung (Belegkette 066): reine Kennzeichnung des Entwurfs, keine Anrechnungslogik.
        if (! empty($data['mark_partial']) && $invoice->type === Invoice::TYPE_INVOICE) {
            $invoice->update(['type' => Invoice::TYPE_PARTIAL]);
        }

        // Zahlungsziel je Rechnung (MVP-163): steuert due_on bei issue()/markSent().
        if (isset($data['payment_terms_days'])) {
            $invoice->update(['payment_terms_days' => (int) $data['payment_terms_days']]);
        }
        $invoice->update([
            'delivery_format' => $invoice->isProforma()
                ? InvoiceDeliveryFormat::Pdf
                : ($data['delivery_format'] ?? $customer->delivery_format ?? InvoiceDeliveryFormat::Pdf),
            'buyer_reference' => $data['buyer_reference'] ?? null,
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', match ($data['content'] ?? 'service') {
            'proforma' => __('Pro-forma-Entwurf erstellt.'),
            'down_payment' => __('Abschlagsrechnungs-Entwurf erstellt.'),
            'material' => __('Materialrechnungs-Entwurf erstellt.'),
            default => __('Rechnungsentwurf erstellt.'),
        });
    }

    public function show(Invoice $invoice): View {
        Gate::authorize('view', $invoice);
        $invoice->load(['items.timeEntries.user', 'items.article', 'customer', 'project']);

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
    public function dunForm(\Illuminate\Http\Request $request, Invoice $invoice): View {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        abort_unless($invoice->isOverdue() && (int) $invoice->dunning_level < 3 && ! $invoice->isDunningBlocked(), 422);
        $invoice->load('customer');

        $nextLevel = (int) $invoice->dunning_level + 1;
        // Stufen-Defaults der Org-Konfiguration (MVP-691) als Vorbelegung —
        // der Nutzer kann sie im Dialog übersteuern (Override bleibt).
        $dunning = app(\App\Services\Invoicing\DunningService::class);
        $step = $dunning->stepConfig($nextLevel);
        $interest = $dunning->interest($invoice);

        // KI-Mahntext-Entwurf (Feature 084, MVP-405-Rest): ?ki=1 lädt den
        // Dialog mit vorbefülltem Zusatztext — nie Auto-Versand.
        [$aiUsable, $aiText, $aiError] = $this->coveringTextFor(
            $request,
            \App\Services\Ai\Suggestions\CoveringTextSuggestionService::CAPABILITY_DUNNING_TEXT,
            fn (): string => app(\App\Services\Ai\Suggestions\CoveringTextSuggestionService::class)->suggestDunningText($invoice, $nextLevel),
        );

        return view('invoices._dun_dialog', [
            'invoice' => $invoice,
            'nextLevel' => $nextLevel,
            'defaultTo' => $invoice->customer->primaryContact()['email'] ?? $invoice->customer->email ?? '',
            'defaultFee' => $step['fee'] > 0 ? number_format($step['fee'], 2, '.', '') : null,
            'defaultPayUntil' => now()->addDays($step['pay_days'])->toDateString(),
            'interest' => $interest,
            'aiUsable' => $aiUsable,
            'aiText' => $aiText,
            'aiError' => $aiError,
        ]);
    }

    /**
     * Gemeinsamer KI-Zweig der Dialog-Loader (Feature 084, MVP-405-Rest):
     * prüft die Capability-Freischaltung und holt bei ?ki=1 synchron den
     * Entwurf; Fehler werden als Hinweis in den Dialog gereicht.
     *
     * @param  callable(): string  $suggest
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    private function coveringTextFor(\Illuminate\Http\Request $request, string $capability, callable $suggest): array {
        $usable = app(\App\Services\Ai\Suggestions\SuggestionViewData::class)->capabilityUsable($capability);
        $text = null;
        $error = null;

        if ($usable && $request->boolean('ki')) {
            try {
                $text = $suggest();
            } catch (\App\Services\Ai\Exceptions\AiException $e) {
                $error = $e->getMessage();
            }
        }

        return [$usable, $text !== '' ? $text : null, $error];
    }

    /** Mahnstufe erhöhen (MVP-163): nur für überfällige Rechnungen, max. 3. */
    public function dun(Request $request, Invoice $invoice): RedirectResponse {
        // Mahnen betrifft AUSGESTELLTE Rechnungen — die issue-Policy (nur
        // draft) passt nicht; Maßstab ist das Abrechnungsrecht.
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);

        $data = $request->validate([
            'send_mail' => ['nullable', 'boolean'],
            'email' => ['nullable', 'required_if_accepted:send_mail', 'email:rfc'],
            'note' => ['nullable', 'string', 'max:1000'],
            // MVP-650: optionale Mahngebühr + Zahlungsziel fürs Mahnschreiben.
            'fee' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'pay_until' => ['nullable', 'date'],
        ]);

        // Vollzug im DunningService (MVP-691): eingegebene Gebühr/Frist sind
        // Override — die Dialog-Vorbelegung kam bereits aus der Org-Konfiguration.
        try {
            $result = app(\App\Services\Invoicing\DunningService::class)->dunInvoice($invoice, [
                'fee' => isset($data['fee']) && is_numeric($data['fee']) ? (float) $data['fee'] : null,
                'pay_until' => ! empty($data['pay_until']) ? \Carbon\CarbonImmutable::parse((string) $data['pay_until']) : null,
                'note' => $data['note'] ?? null,
                'send_mail' => ! empty($data['send_mail']),
                'email' => isset($data['email']) ? (string) $data['email'] : null,
            ]);
        } catch (\App\Services\Invoicing\DunningException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['mailed']) {
            return redirect()->route('invoices.show', $invoice)
                ->with('status', __('Mahnstufe :level vermerkt und Mahnung an :email versendet.', ['level' => $result['level'], 'email' => (string) ($data['email'] ?? '')]));
        }

        return redirect()->route('invoices.show', $invoice)->with('status', __('Mahnstufe :level vermerkt.', ['level' => $result['level']]));
    }

    /**
     * Mahnsperre umschalten (Feature 127, MVP-691): gesperrte Rechnungen
     * bleiben im Mahnlauf und im Einzeldialog außen vor.
     */
    public function toggleDunningBlock(Invoice $invoice): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);

        if ($invoice->isDunningBlocked()) {
            $invoice->update(['dunning_blocked_at' => null]);
            $invoice->audit('invoice.dunningUnblocked', ['by' => (int) Auth::id()]);

            return back()->with('status', __('finance.dunning.flash_unblocked', ['nr' => (string) $invoice->number]));
        }

        $invoice->update(['dunning_blocked_at' => now()]);
        $invoice->audit('invoice.dunningBlocked', ['by' => (int) Auth::id()]);

        return back()->with('status', __('finance.dunning.flash_blocked', ['nr' => (string) $invoice->number]));
    }

    /**
     * Zustellversuch protokollieren (MVP-168): Kanal/Format/Empfänger/Hash.
     *
     * @param  array<string, mixed>  $meta
     */
    private function recordDispatch(Invoice $invoice, string $channel, ?string $format, ?string $recipient, ?string $sha256, array $meta = []): \App\Models\DocumentDispatch {
        return \App\Models\DocumentDispatch::query()->create([
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'document_kind' => \App\Enums\DocumentDesign\RenderDocumentKind::Invoice->value,
            'document_id' => $invoice->id,
            'channel' => $channel,
            'format' => $format,
            'status' => $channel === \App\Models\DocumentDispatch::CHANNEL_EMAIL ? 'queued' : 'sent',
            'recipient' => $recipient,
            'sha256' => $sha256,
            'meta' => $meta !== [] ? $meta : null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Widerspruch gegen eine (umsatzsteuerliche) Gutschrift dokumentieren
     * (§ 14 Abs. 2 UStG; Feature 066, Vollaudit 2026-07 M27): Lifecycle-
     * Vermerk mit Pflichtnote — der festgeschriebene Beleg bleibt unverändert.
     */
    public function documentObjection(Request $request, Invoice $invoice): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);

        if (! $invoice->isCreditNote()) {
            return back()->with('error', __('Ein Widerspruch ist nur bei Gutschriften dokumentierbar.'));
        }
        if ($invoice->objection_at !== null) {
            return back()->with('error', __('Für diese Gutschrift ist bereits ein Widerspruch dokumentiert.'));
        }

        $data = $request->validate([
            'objection_note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $invoice->update([
            'objection_at' => now(),
            'objection_note' => (string) $data['objection_note'],
        ]);
        $invoice->audit('invoice.objectionDocumented', ['note' => (string) $data['objection_note']]);

        return back()->with('status', __('Widerspruch dokumentiert — die Gutschrift verliert ihre Wirkung als Rechnung.'));
    }

    public function issue(Invoice $invoice): RedirectResponse {
        Gate::authorize('issue', $invoice);

        // Ausstellung läuft über die einzige Schreibstelle (Vollscan 2026-08-23,
        // B1): Pro-forma-Guard, Freigabepflicht (MVP-163), E-Rechnungs-
        // Validierung (MVP-164), Partei-Snapshot + tax_context-Freeze (MVP-162/243).
        $issuer = app(InvoiceIssueService::class);
        try {
            $issuer->assertIssuable($invoice);
        } catch (InvoiceIssueException $e) {
            if ($e->reason === InvoiceIssueException::REASON_EINVOICE_INVALID) {
                return redirect()->route('invoices.einvoice-validation', $invoice)->with('error', $e->getMessage());
            }

            return back()->with('error', $e->getMessage());
        }

        $issuer->issue($invoice);

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
            ->forReferenceable($invoice)
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
            'sha256' => CryptoHelper::hash($xml),
        ]);
        $this->recordDispatch($invoice, \App\Models\DocumentDispatch::CHANNEL_DOWNLOAD, 'xrechnung_ubl', null, CryptoHelper::hash($xml), ['filename' => $filename]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * GAEB-Rechnung (X89) bzw. rechnungsbegründende Unterlage (X89B).
     *
     * **Keine zweite Rechnungshoheit** (D8): Führt ein externes System, bleibt
     * es führend — dann gibt es hier auch keine vollständige Rechnung
     * auszugeben, und der Download entfällt wie bei der XRechnung.
     *
     * Anders als dort ist die **Pro-forma-Rechnung zulässig**: GAEB kennt sie
     * als eigene Art, und die Datei sagt über `InvoiceType` selbst, dass sie
     * keine Zahlung fordert. Eine XRechnung könnte das nicht.
     */
    public function gaebDownload(Invoice $invoice, \App\Services\Gaeb\GaebInvoiceExportService $export, Request $request): SymfonyResponse {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'organization']);

        $billingMode = app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($invoice->customer);
        abort_if($billingMode->isExternal(), 404);

        // X89B ist die Anlage zu einer Rechnung, keine Rechnung selbst.
        $phase = $request->string('format')->lower()->toString() === 'x89b'
            ? \ERechnungToolkit\Enums\GaebPhase::InvoiceAttachment
            : \ERechnungToolkit\Enums\GaebPhase::Invoice;

        $result = $export->export($invoice, $phase);

        $invoice->audit('invoice.einvoice_exported', [
            'format' => 'gaeb_' . strtolower($phase->value),
            'filename' => $result['filename'],
            'sha256' => CryptoHelper::hash($result['content']),
            'losses' => $result['losses'],
        ]);
        $this->recordDispatch(
            $invoice,
            \App\Models\DocumentDispatch::CHANNEL_DOWNLOAD,
            'gaeb_' . strtolower($phase->value),
            null,
            CryptoHelper::hash($result['content']),
            ['filename' => $result['filename']],
        );

        return response($result['content'], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
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
            'sha256' => CryptoHelper::hash($pdf),
        ]);
        $this->recordDispatch($invoice, \App\Models\DocumentDispatch::CHANNEL_DOWNLOAD, 'zugferd_pdf', null, CryptoHelper::hash($pdf), ['filename' => $filename]);

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
            'articles' => \App\Models\Article::query()->where('sellable', true)->orderBy('name')->limit(500)->get(['id', 'number', 'name', 'base_unit', 'default_sale_price', 'currency']),
        ]);
    }

    public function addItem(SaveInvoiceItemRequest $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);
        $data = $request->validated();

        $invoice->items()->create([
            'organization_id' => $invoice->organization_id,
            'article_id' => $data['article_id'] ?? null,
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

        $oldDescription = (string) $item->description;

        $item->update([
            'article_id' => $data['article_id'] ?? null,
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

        // Wörterbuch-Kandidaten aus der manuellen Korrektur — Aufnahme NUR
        // über den bestätigten „Merken?"-Dialog, nie still.
        $pairs = \App\Services\Invoicing\TextCorrectionDiff::candidates($oldDescription, (string) $data['description']);
        if ($pairs !== []) {
            session()->flash('text_correction_learn', ['pairs' => $pairs]);
        }

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
    public function sendForm(\Illuminate\Http\Request $request, Invoice $invoice): View {
        Gate::authorize('send', $invoice);
        $invoice->load(['customer', 'items']);

        // KI-Begleittext-Entwurf (Feature 084, MVP-405-Rest): ?ki=1 lädt den
        // Dialog mit vorbefülltem custom_text — nie Auto-Versand.
        [$aiUsable, $aiText, $aiError] = $this->coveringTextFor(
            $request,
            \App\Services\Ai\Suggestions\CoveringTextSuggestionService::CAPABILITY_MAIL_TEXT,
            fn (): string => app(\App\Services\Ai\Suggestions\CoveringTextSuggestionService::class)->suggestMailText($invoice),
        );

        $templates = InvoiceMailTemplate::query()
            ->forKind(\App\Enums\DocumentDesign\RenderDocumentKind::Invoice)
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
            'aiUsable' => $aiUsable,
            'aiText' => $aiText,
            'aiError' => $aiError,
        ]);
    }

    /**
     * Versendet die Rechnung. Multi-Empfänger (To/CC), automatisches BCC an
     * Absender, optional Statusübergang draft→issued, Queue, PDF-Anhang.
     */
    public function send(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('send', $invoice);

        // Der Versand stellt einen Entwurf aus (markSent) — dieselben
        // Voraussetzungen wie issue(), sonst verließe eine unfreigegebene
        // Rechnung das Haus (Vollscan 2026-08-23, B1).
        $issuer = app(InvoiceIssueService::class);
        if ($issuer->wouldIssue($invoice)) {
            try {
                $issuer->assertIssuable($invoice);
            } catch (InvoiceIssueException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        // Feature 128 (MVP-692): Der Dialog postet die Vorlage als Sqid
        // (InvoiceMailTemplate nutzt HasSqid); rohe IDs (Tests/API) bleiben gültig.
        $rawTemplate = $request->input('template_id');
        if (is_string($rawTemplate) && $rawTemplate !== '' && ! ctype_digit($rawTemplate)) {
            $request->merge(['template_id' => app(\App\Services\SqidEncoder::class)->decode(InvoiceMailTemplate::class, $rawTemplate)]);
        }

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
            'delivery_format' => ['nullable', \Illuminate\Validation\Rule::enum(InvoiceDeliveryFormat::class)],
        ]);

        $deliveryFormat = InvoiceDeliveryFormat::tryFrom((string) ($data['delivery_format'] ?? ''))
            ?? $invoice->delivery_format
            ?? InvoiceDeliveryFormat::Pdf;
        if ($invoice->isProforma() && $deliveryFormat->isElectronic()) {
            return back()->withInput()->with('error', __('invoice-import.error.proforma'));
        }
        if ($deliveryFormat->isElectronic()) {
            $invoice->loadMissing(['items', 'customer']);
            // Der Queue-Job sieht nach markSent() den gestellten Datensatz. Für
            // den synchronen Preflight spiegeln wir diesen Status nur im RAM.
            $validationInvoice = clone $invoice;
            if ($invoice->status === Invoice::STATUS_DRAFT && ! $invoice->isCreditNote()) {
                $validationInvoice->status = Invoice::STATUS_ISSUED;
                $validationInvoice->issued_on ??= now();
                $validationInvoice->due_on ??= now()->addDays($validationInvoice->payment_terms_days ?? 14);
            }
            $generator = app(\App\Services\Invoicing\EInvoice\XRechnungGenerator::class);
            $profile = $deliveryFormat->needsZugferd()
                ? \ERechnungToolkit\Enums\ERechnungProfile::EN16931
                : \ERechnungToolkit\Enums\ERechnungProfile::XRECHNUNG;
            $preflight = $generator->preflight($validationInvoice, $profile);
            if ($preflight['errors'] !== []) {
                return back()->withInput()->with('error', __('invoicing.einvoice.error_intro') . ' ' . implode(' ', $preflight['errors']));
            }
            if ($deliveryFormat->needsZugferd() && ! $generator->zugferdAvailable()) {
                return back()->withInput()->with('error', __('invoicing.einvoice.zugferd.unavailable'));
            }
        }

        /** @var InvoiceMailTemplate $template */
        $template = InvoiceMailTemplate::query()->findOrFail($data['template_id']);
        // Org-Sicherheit: globale Templates oder Templates der eigenen Org
        if ($template->organization_id !== null && $template->organization_id !== $invoice->organization_id) {
            abort(403);
        }
        // Feature 128 (MVP-692): Vorlagen gelten je Belegart.
        abort_unless($template->document_kind === \App\Enums\DocumentDesign\RenderDocumentKind::Invoice->value, 422, (string) __('Vorlage passt nicht zur Belegart.'));

        // Belegsprache je Kunde (Feature 034, MVP-721): Vorlagen-Platzhalter
        // (Belegart, Datum) in der Sprache des Kunden.
        $rendered = \App\Support\DocumentLocale::within($invoice->customer, $invoice->organization, fn (): array => $template->renderForInvoice($invoice, $data['custom_text'] ?? null));

        $bcc = $data['bcc'] ?? [];
        if (! empty($data['bcc_sender'])) {
            $senderAddr = (string) config('mail.from.address');
            if ($senderAddr !== '' && ! in_array($senderAddr, $bcc, true)) {
                $bcc[] = $senderAddr;
            }
        }

        // Zustellnachweis (MVP-168): jeder Versand ist ein eigener Versuch.
        // Vollaudit 2026-07 (M26): Dispatch VOR dem Queuen — Status/Message-ID/
        // Dateihash schreibt der Versandpfad (Listener + Mailable) nach.
        $dispatch = $this->recordDispatch($invoice, \App\Models\DocumentDispatch::CHANNEL_EMAIL, $deliveryFormat->dispatchFormat(), implode(', ', $data['to']), null, [
            'cc' => $data['cc'] ?? [],
            'template_id' => $template->id,
        ]);

        $mail = new InvoiceMail($invoice, $rendered['subject'], $rendered['html'], $rendered['text'], (int) $dispatch->id, $deliveryFormat);
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
