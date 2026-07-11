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
use App\Models\{Customer, User};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Attachments\FileAttacher;
use App\Services\Claims\ClaimCaseService;
use App\Services\Classification\ClassificationResolver;
use App\Support\Sqid;
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
    public function __construct(private readonly ClaimCaseService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClaimCase::class);

        $statusFilter = ClaimStatus::tryFrom($request->string('status')->toString())?->value;

        return view('claims.index', [
            'cases' => ClaimCase::query()
                ->with(['customer', 'responsible'])
                ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
                ->when($request->filled('overdue'), fn($q) => $q->overdue())
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'openCount' => ClaimCase::query()->open()->count(),
            'overdueCount' => ClaimCase::query()->overdue()->count(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(ClaimCase $claim): View {
        Gate::authorize('view', $claim);

        $claim->load([
            'customer', 'responsible', 'invoice', 'asset', 'article', 'supplier',
            'diaryEntry', 'project', 'serviceTicket', 'protocol', 'stockSerial',
            'defectType', 'rootCause', 'goodwillReason',
            'evidence.recorder', 'assessments.assessor', 'decisions.decider',
            'rmaReturns.inspections', 'actions.assignee',
            'financialOutcomes.resultInvoice', 'supplierRecourses.supplier',
            'links', 'attachments',
        ]);

        $resolver = app(ClassificationResolver::class);
        $orgId = (int) $claim->organization_id;

        return view('claims.show', [
            'claim' => $claim,
            'defectTypes' => $resolver->list($orgId, ClassificationDomain::DefectType),
            'rootCauses' => $resolver->list($orgId, ClassificationDomain::RootCause),
            'goodwillReasons' => $resolver->list($orgId, ClassificationDomain::GoodwillReason),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'duplicates' => $this->service->duplicates($claim->only([
                'diary_entry_id', 'invoice_id', 'asset_id', 'stock_serial_id', 'serial_no', 'customer_id', 'title',
            ]), (int) $claim->id),
        ]);
    }

    /** Anlege-Dialog (Formulare in Dialogen, MVP-247). */
    public function create(): View {
        Gate::authorize('create', ClaimCase::class);

        return view('claims._form_dialog', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
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

        $claim->fill($this->validated($request))->save();

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
            return back()->withErrors(['decision' => $e->getMessage()]);
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
            return back()->withErrors(['status' => $e->getMessage()]);
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

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $fieldModels = [
            'customer_id' => \App\Models\Customer::class,
            'responsible_user_id' => \App\Models\User::class,
            'diary_entry_id' => \App\Models\DiaryEntry::class,
            'project_id' => \App\Models\Project::class,
            'service_ticket_id' => \App\Models\ServiceTicket::class,
            'protocol_id' => \App\Models\Protocol::class,
            'asset_id' => \App\Models\Asset::class,
            'article_id' => \App\Models\Article::class,
            'invoice_id' => \App\Models\Invoice::class,
            'supplier_id' => \App\Models\Supplier::class,
            'purchase_order_id' => \App\Models\PurchaseOrder::class,
            'stock_serial_id' => \App\Models\StockSerial::class,
            'defect_type_classification_id' => \App\Models\Classification::class,
            'root_cause_classification_id' => \App\Models\Classification::class,
            'goodwill_reason_classification_id' => \App\Models\Classification::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        return $request->validate([
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
            // Katalog mit Org-Override (D3): globale Zeilen (org NULL) + eigene.
            'defect_type_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
            'root_cause_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
            'goodwill_reason_classification_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('classifications', includeGlobal: true)],
        ]);
    }
}
