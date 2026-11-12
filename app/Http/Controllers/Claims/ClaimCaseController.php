<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimCaseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Claims;

use App\Enums\Claims\{ClaimKind, ClaimSource, ClaimStatus, ClaimVerdict};
use App\Enums\Classification\ClassificationDomain;
use App\Http\Controllers\Controller;
use App\Models\Claims\{ClaimCase, ClaimDecision};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Attachments\FileAttacher;
use App\Services\Claims\{ClaimCaseService, ClaimFinancialService};
use App\Services\Claims\Contracts\RmaReturnLabelIssuer;
use App\Services\Classification\ClassificationResolver;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{ErrorText, Sqid};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Reklamationsakten (Feature 072, MVP-247–249): Liste mit Status/Frist,
 * Fallakte mit Nachweisen/Bewertung/Entscheidung/Maßnahmen; Anlegen und
 * Bearbeiten als Dialog. Dublettenwarnung beim Eingang (MVP-248).
 */
class ClaimCaseController extends Controller {
    /** Betroffene Objekte der Akte (MVP-1577). */
    private const array LINK_FIELDS = [
        'diary_entry_id', 'project_id', 'service_ticket_id', 'protocol_id', 'asset_id',
        'article_id', 'invoice_id', 'supplier_id', 'purchase_order_id',
    ];

    public function __construct(private readonly ClaimCaseService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClaimCase::class);

        $statusFilter = ClaimStatus::tryFrom($request->string('status')->toString())?->value;
        // Kachel „Offene Fälle“ (ux-pattern-katalog §4): offen sind vier Status, die die Status-Auswahl einzeln kennt.
        $showOpen = $request->string('show')->toString() === 'open';

        return view('claims.index', [
            'showOpen' => $showOpen,
            'cases' => ClaimCase::query()
                ->with(['customer', 'responsible'])
                ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
                ->when($showOpen, fn($q) => $q->open())
                ->when($request->filled('overdue'), fn($q) => $q->overdue())
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'openCount' => ClaimCase::query()->open()->count(),
            'overdueCount' => ClaimCase::query()->overdue()->count(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(ClaimCase $claim): View {
        Gate::authorize('view', $claim);

        $claim->load([
            'customer', 'responsible', 'invoice', 'asset', 'article', 'supplier',
            'diaryEntry', 'project', 'serviceTicket', 'protocol', 'stockSerial', 'stockLot',
            'defectType', 'rootCause', 'goodwillReason',
            'evidence.recorder', 'assessments.assessor', 'decisions.decider',
            'rmaReturns.inspections', 'rmaReturns.returnShipments', 'rmaReturns.articleVariant', 'rmaReturns.article',
            'rmaReturns.warehouse', 'rmaReturns.stockLot', 'actions.assignee', 'actions.followUp',
            'financialOutcomes.resultInvoice', 'financialOutcomes.invoice', 'supplierRecourses.supplier',
            'supplierRecourses.purchaseOrder', 'supplierRecourses.incomingEInvoice', 'supplierRecourses.article', 'purchaseOrder',
            'links', 'attachments',
        ]);

        $resolver = app(ClassificationResolver::class);
        $orgId = (int) $claim->organization_id;

        return view('claims.show', [
            'claim' => $claim,
            'defectTypes' => $resolver->list($orgId, ClassificationDomain::DefectType),
            'rootCauses' => $resolver->list($orgId, ClassificationDomain::RootCause),
            'goodwillReasons' => $resolver->list($orgId, ClassificationDomain::GoodwillReason),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'returnCarriers' => $claim->organization !== null ? app(RmaReturnLabelIssuer::class)->carriers($claim->organization) : [],
            'stock' => $this->stockOptions($claim),
            'financialInvoices' => \App\Models\Invoicing\Invoice::query()
                ->where(fn ($q) => $q->whereKey($claim->invoice_id ?? 0)->when($claim->customer_id !== null, fn ($w) => $w->orWhere('customer_id', $claim->customer_id)))
                ->orderByDesc('id')->limit(100)->get(['id', 'number', 'issued_on', 'status', 'customer_id']),
            'localBilling' => app(ClaimFinancialService::class)->localBilling($claim),
            'recourseOptions' => Gate::allows('recourse', $claim) ? $this->recourseOptions() : null,
            'duplicates' => $this->service->duplicates($claim->only([
                'diary_entry_id', 'invoice_id', 'asset_id', 'stock_serial_id', 'serial_no', 'customer_id', 'title',
            ]), (int) $claim->id),
            // Folgeaufträge der Maßnahmen (MVP-1623): sichtbare Aufträge beim Kunden der Akte.
            'followUpOrders' => \App\Models\Diary\DiaryEntry::query()->visibleInBulkTo($this->authUser())
                ->when($claim->customer_id !== null, fn ($q) => $q->where('customer_id', $claim->customer_id))
                ->orderByDesc('id')->limit(100)->get(['id', 'title', 'customer_id', 'user_id', 'assigned_user_id']),
        ]);
    }

    /**
     * Anlege-Dialog (Formulare in Dialogen, MVP-247). Die Einstiege an Rechnung,
     * Auftrag, Asset und Ticket übergeben ihr Objekt als Sqid (MVP-1577).
     */
    public function create(Request $request): View {
        Gate::authorize('create', ClaimCase::class);

        return $this->formDialog(null, $this->prefillFromOrigin($request));
    }

    public function edit(ClaimCase $claim): View {
        Gate::authorize('update', $claim);

        return $this->formDialog($claim, $claim->only([...self::LINK_FIELDS, 'customer_id', 'responsible_user_id', 'serial_no', 'stock_lot_id']));
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ClaimCase::class);

        $actor = $request->user() ?? abort(401);
        $organization = $actor->organization ?? abort(403);
        $data = $this->validated($request);
        $case = $this->service->open($organization, $actor, $data);

        $duplicates = $this->service->duplicates($data, (int) $case->id);
        $notice = $duplicates->isNotEmpty()
            ? __('Reklamation :number angelegt — mögliche Dubletten: :numbers', ['number' => $case->number, 'numbers' => $duplicates->pluck('number')->implode(', ')])
            : __('Reklamation :number angelegt.', ['number' => $case->number]);

        return redirect()->route('claims.show', $case)->with('status', $notice);
    }

    public function update(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('update', $claim);

        $data = $this->validated($request);
        // Das Dialogfeld kennt nur den Tag: eine unveränderte Frist behält ihre Uhrzeit.
        if (isset($data['due_at']) && $claim->due_at?->isSameDay($data['due_at']) === true) {
            unset($data['due_at']);
        }
        $claim->fill($data)->save();

        return back()->with('status', __('Reklamationsakte aktualisiert.'));
    }

    /** Bewertung (MVP-249): Anspruchsart + Ergebnis + Pflichtbegründung. */
    public function assess(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('decide', $claim);

        $data = $request->validate([
            'claim_kind' => ['required', Rule::enum(ClaimKind::class)],
            'verdict' => ['required', Rule::enum(ClaimVerdict::class)],
            'justification' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $this->service->assess($claim, $request->user() ?? abort(401), ClaimKind::from($data['claim_kind']), ClaimVerdict::from($data['verdict']), $data['justification']);

        return back()->with('status', __('Bewertung festgehalten (Snapshot eingefroren).'));
    }

    /** Entscheidung (MVP-249) mit Pflichtbegründung und Auditspur. */
    public function decide(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('decide', $claim);

        $data = $request->validate([
            'decision' => ['required', Rule::in(ClaimDecision::DECISIONS)],
            'justification' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        try {
            $this->service->decide($claim, $request->user() ?? abort(401), $data['decision'], $data['justification']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['decision' => ErrorText::for($e)]);
        }

        return back()->with('status', __('Entscheidung dokumentiert.'));
    }

    public function transition(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('update', $claim);

        $data = $request->validate(['status' => ['required', Rule::enum(ClaimStatus::class)]]);

        try {
            $target = ClaimStatus::from($data['status']);
            if ($target === ClaimStatus::Closed) {
                $this->service->close($claim, $request->user() ?? abort(401));
            } else {
                $this->service->transition($claim, $target);
            }
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('status', __('Status aktualisiert.'));
    }

    /** Nachweis (MVP-249): fachlicher Eintrag + optionale Datei. */
    public function storeEvidence(Request $request, ClaimCase $claim): RedirectResponse {
        Gate::authorize('update', $claim);

        $data = $request->validate([
            'kind' => ['required', Rule::in(\App\Models\Claims\ClaimEvidence::KINDS)],
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:4000'],
            'file' => ['nullable', ...FileAttacher::rule()],
        ]);

        $actor = $request->user() ?? abort(401);
        $claim->evidence()->create([
            'organization_id' => $claim->organization_id,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'note' => $data['note'] ?? null,
            'recorded_by' => $actor->id,
            'recorded_at' => now(),
        ]);
        if ($request->hasFile('file')) {
            app(FileAttacher::class)->store($claim, $request->file('file'), $actor->id);
        }

        return back()->with('status', __('Nachweis erfasst.'));
    }

    /**
     * Vorbelegung aus dem Einstieg: nur Objekte, die der Nutzer sehen darf;
     * Kunde, Projekt und Seriennummer folgen dem Objekt.
     *
     * @return array<string, int|string|null>
     */
    private function prefillFromOrigin(Request $request): array {
        $origins = [
            'invoice' => ['invoice_id', \App\Models\Invoicing\Invoice::class],
            'order' => ['diary_entry_id', \App\Models\Diary\DiaryEntry::class],
            'asset' => ['asset_id', \App\Models\Asset\Asset::class],
            'ticket' => ['service_ticket_id', \App\Models\ServiceTicket\ServiceTicket::class],
        ];
        $prefill = [];
        foreach ($origins as $param => [$field, $model]) {
            $id = Sqid::decode($model, $request->string($param)->toString());
            $origin = $id !== null ? $model::query()->find($id) : null;
            if ($origin === null || ! Gate::allows('view', $origin)) {
                continue;
            }
            $prefill[$field] = (int) $origin->getKey();
            $prefill['customer_id'] ??= $origin->getAttribute('customer_id');
            foreach (['project_id', 'diary_entry_id', 'asset_id'] as $follow) {
                if ($follow !== $field && $origin->getAttribute($follow) !== null) {
                    $prefill[$follow] ??= (int) $origin->getAttribute($follow);
                }
            }
            if ($origin instanceof \App\Models\Asset\Asset) {
                $prefill['serial_no'] = $origin->serial_no;
            }
        }

        return $prefill;
    }

    /**
     * Dialog mit den Auswahllisten der betroffenen Objekte: die jüngsten je
     * Art, dazu immer das bereits verknüpfte. Aufträge nur, soweit sichtbar.
     *
     * @param array<string, mixed> $values
     */
    private function formDialog(?ClaimCase $claim, array $values): View {
        $user = $this->authUser();
        $recent = static fn (\Illuminate\Database\Eloquent\Builder $query, string $field, int $limit = 100) => $query
            ->orderByDesc('id')->limit($limit)->get()
            ->when(($values[$field] ?? null) !== null, fn ($rows) => $rows->contains('id', (int) $values[$field])
                ? $rows
                : $rows->concat($query->getModel()->newQuery()->whereKey((int) $values[$field])->get()));
        $stock = app(FeatureFlagResolver::class)->isEnabled('module.lager');

        return view('claims._form_dialog', [
            'claim' => $claim,
            'values' => $values,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'diaryEntries' => $recent(\App\Models\Diary\DiaryEntry::query()->visibleInBulkTo($user)->select(['id', 'title', 'customer_id', 'user_id', 'assigned_user_id']), 'diary_entry_id'),
            'projects' => \App\Models\Project\Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
            'tickets' => $recent(\App\Models\ServiceTicket\ServiceTicket::query()->select(['id', 'ticket_no', 'title', 'customer_id']), 'service_ticket_id'),
            'protocols' => $recent(\App\Models\Protocol\Protocol::query()->select(['id', 'title']), 'protocol_id'),
            'assets' => \App\Models\Asset\Asset::query()->orderBy('name')->get(['id', 'name', 'serial_no', 'customer_id']),
            'invoices' => $recent(\App\Models\Invoicing\Invoice::query()->select(['id', 'number', 'customer_id', 'issued_on']), 'invoice_id', 200),
            'suppliers' => \App\Models\Supplier\Supplier::query()->orderBy('name')->get(['id', 'name']),
            'articles' => $stock ? \App\Models\Article\Article::query()->orderBy('name')->get(['id', 'number', 'name']) : collect(),
            // Chargen nur chargengeführter Artikel (MVP-1626); die verknüpfte immer dabei.
            'lots' => $stock ? $recent(\App\Models\Inventory\StockLot::query()->with('variant.article:id,name')
                ->whereHas('variant.article', fn ($q) => $q->where('batch_required', true))
                ->select(['id', 'article_variant_id', 'lot_no']), 'stock_lot_id', 300) : collect(),
            'purchaseOrders' => $stock ? $recent(\App\Models\Procurement\PurchaseOrder::query()->select(['id', 'number', 'supplier_id']), 'purchase_order_id') : collect(),
        ]);
    }

    /**
     * Auswahllisten des Regressdialogs (MVP-1577); Bestellung und Artikel nur mit Lager-Modul.
     *
     * @return array{suppliers: \Illuminate\Support\Collection<int, \App\Models\Supplier\Supplier>, purchaseOrders: \Illuminate\Support\Collection<int, \App\Models\Procurement\PurchaseOrder>, incomingInvoices: \Illuminate\Support\Collection<int, \App\Models\Invoicing\IncomingEInvoice>, articles: \Illuminate\Support\Collection<int, \App\Models\Article\Article>}
     */
    private function recourseOptions(): array {
        $stock = app(FeatureFlagResolver::class)->isEnabled('module.lager');

        return [
            'suppliers' => \App\Models\Supplier\Supplier::query()->orderBy('name')->get(['id', 'name']),
            'purchaseOrders' => $stock
                ? \App\Models\Procurement\PurchaseOrder::query()->with('supplier:id,name')->orderByDesc('id')->limit(100)->get(['id', 'number', 'supplier_id'])
                : collect(),
            'incomingInvoices' => \App\Models\Invoicing\IncomingEInvoice::query()->purchases()->visibleTo($this->authUser())
                ->orderByDesc('id')->limit(100)->get(['id', 'invoice_number', 'seller_name', 'issue_date']),
            'articles' => $stock ? \App\Models\Article\Article::query()->orderBy('name')->get(['id', 'number', 'name']) : collect(),
        ];
    }

    /**
     * Auswahllisten für den Lagerbezug der Rückläufer (MVP-1576); null ohne
     * Lager-Modul oder Lagerrecht — dann dokumentiert der Rückläufer nur.
     *
     * @return array{warehouses: \Illuminate\Support\Collection<int, \App\Models\Inventory\Warehouse>, variants: \Illuminate\Support\Collection<int, \App\Models\Article\ArticleVariant>, serials: \Illuminate\Support\Collection<int, \App\Models\Inventory\StockSerial>, lots: \Illuminate\Support\Collection<int, \App\Models\Inventory\StockLot>}|null
     */
    private function stockOptions(ClaimCase $claim): ?array {
        if (! app(FeatureFlagResolver::class)->isEnabled('module.lager') || ! Gate::allows('warehouse', $claim)) {
            return null;
        }

        $variants = \App\Models\Article\ArticleVariant::query()
            ->with('article:id,name')
            ->when($claim->article_id !== null, fn ($q) => $q->where('article_id', $claim->article_id))
            ->get()
            ->sortBy(fn (\App\Models\Article\ArticleVariant $v): string => ($v->article->name ?? '') . ' ' . ($v->sku ?? ''))
            ->values();
        // Ohne Artikel oder Kunde wäre die Liste beliebig — dann nur die Seriennummer der Akte.
        $serials = \App\Models\Inventory\StockSerial::query()
            ->where(function ($q) use ($claim): void {
                $q->whereKey($claim->stock_serial_id ?? 0);
                if ($claim->article_id !== null || $claim->customer_id !== null) {
                    $q->orWhere(fn ($inner) => $inner
                        ->when($claim->article_id !== null, fn ($w) => $w->where('article_id', $claim->article_id))
                        ->when($claim->customer_id !== null, fn ($w) => $w->where('customer_id', $claim->customer_id)));
                }
            })
            ->orderBy('serial_no')->limit(200)->get(['id', 'article_variant_id', 'serial_no']);

        return [
            'warehouses' => \App\Models\Inventory\Warehouse::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'variants' => $variants,
            'serials' => $serials,
            'lots' => $claim->article_id !== null || $claim->stock_lot_id !== null
                ? \App\Models\Inventory\StockLot::query()
                    ->where(fn ($q) => $q->whereIn('article_variant_id', $variants->modelKeys())->orWhere('id', $claim->stock_lot_id ?? 0))
                    ->orderBy('lot_no')->limit(200)->get(['id', 'article_variant_id', 'lot_no'])
                : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $fieldModels = [
            'customer_id' => \App\Models\Customer\Customer::class,
            'responsible_user_id' => \App\Models\Platform\User::class,
            'diary_entry_id' => \App\Models\Diary\DiaryEntry::class,
            'project_id' => \App\Models\Project\Project::class,
            'service_ticket_id' => \App\Models\ServiceTicket\ServiceTicket::class,
            'protocol_id' => \App\Models\Protocol\Protocol::class,
            'asset_id' => \App\Models\Asset\Asset::class,
            'article_id' => \App\Models\Article\Article::class,
            'invoice_id' => \App\Models\Invoicing\Invoice::class,
            'supplier_id' => \App\Models\Supplier\Supplier::class,
            'purchase_order_id' => \App\Models\Procurement\PurchaseOrder::class,
            'stock_serial_id' => \App\Models\Inventory\StockSerial::class,
            'stock_lot_id' => \App\Models\Inventory\StockLot::class,
            'defect_type_classification_id' => \App\Models\Classification\Classification::class,
            'root_cause_classification_id' => \App\Models\Classification\Classification::class,
            'goodwill_reason_classification_id' => \App\Models\Classification\Classification::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'source' => ['required', Rule::enum(ClaimSource::class)],
            'priority' => ['required', Rule::in(ClaimCase::PRIORITIES)],
            'severity' => ['required', Rule::in(ClaimCase::SEVERITIES)],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'is_b2b' => ['sometimes', 'boolean'],
            'reported_at' => ['nullable', 'date'],
            'complaint_notice_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'diary_entry_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'service_ticket_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('service_tickets')],
            'protocol_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('protocols')],
            'asset_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('assets')],
            'article_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('articles')],
            'invoice_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('invoices')],
            'supplier_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'purchase_order_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('purchase_orders')],
            'stock_serial_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('stock_serials')],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'stock_lot_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('stock_lots')],
            // Katalog mit Org-Override (D3): globale Zeilen (org NULL) + eigene.
            'defect_type_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
            'root_cause_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
            'goodwill_reason_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
        ]);

        // Aufträge anderer lassen sich nicht über die Akte anhängen.
        if (($data['diary_entry_id'] ?? null) !== null && ! Gate::allows('view', \App\Models\Diary\DiaryEntry::query()->findOrFail($data['diary_entry_id']))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['diary_entry_id' => __('validation.exists', ['attribute' => __('validation.attributes.diary_entry_id')])]);
        }

        // Charge (MVP-1626): gehört zum Artikel der Akte; ohne Artikel folgt er der Charge.
        if (($data['stock_lot_id'] ?? null) !== null) {
            $lotArticleId = (int) \App\Models\Inventory\StockLot::query()->with('variant:id,article_id')->findOrFail((int) $data['stock_lot_id'])->variant?->article_id;
            if (($data['article_id'] ?? null) !== null && (int) $data['article_id'] !== $lotArticleId) {
                throw \Illuminate\Validation\ValidationException::withMessages(['stock_lot_id' => __('claims.form.lot_article_mismatch')]);
            }
            $data['article_id'] ??= $lotArticleId;
        }

        // Seriennummer als Text: dem Lager zuordnen, wenn sie dort eindeutig ist — Dublettenprüfung und Seriennummernabgleich brauchen den Bezug.
        if (array_key_exists('serial_no', $data) && ! array_key_exists('stock_serial_id', $data)) {
            $serialNo = trim((string) $data['serial_no']);
            $matches = $serialNo === '' ? collect() : \App\Models\Inventory\StockSerial::query()->where('serial_no', $serialNo)
                ->when(($data['article_id'] ?? null) !== null, fn ($q) => $q->where('article_id', $data['article_id']))
                ->limit(2)->get(['id', 'article_id']);
            $data['stock_serial_id'] = $matches->count() === 1 ? $matches->first()->id : null;
            if ($matches->count() === 1 && array_key_exists('article_id', $data)) {
                $data['article_id'] ??= $matches->first()->article_id;
            }
        }

        return $data;
    }
}
