<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Contract;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Http\Controllers\Controller;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\{Customer, Document, Supplier, User};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Contract\ContractService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Allgemeine Vertragsverwaltung (Welle D, CLM): Vertragsbestand mit Status
 * und nächstem Kündigungstermin, Vertragsakte mit Obligationen-/Vertrags-
 * kalender und additive Verknüpfung eines Leasing-/Finanzierungsvertrags.
 */
class ContractController extends Controller {
    public function __construct(private readonly ContractService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Contract::class);

        $statusFilter = ContractStatus::tryFrom($request->string('status')->toString())?->value;
        $kindFilter = ContractKind::tryFrom($request->string('kind')->toString())?->value;

        $contracts = Contract::query()
            ->with(['customer', 'supplier', 'responsible'])
            ->when($statusFilter !== null, fn ($q) => $q->where('status', $statusFilter))
            ->when($kindFilter !== null, fn ($q) => $q->where('kind', $kindFilter))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('contracts.index', [
            'contracts' => $contracts,
            'nextTermination' => collect($contracts->items())
                ->mapWithKeys(fn (Contract $c): array => [
                    $c->id => $c->status->isOpen() ? $this->service->nextTerminationDate($c) : null,
                ]),
            'openCount' => Contract::query()->open()->count(),
            'endingSoonCount' => Contract::query()->open()
                ->whereNotNull('ends_on')
                ->whereDate('ends_on', '<=', now()->addMonths(3)->toDateString())
                ->count(),
        ]);
    }

    public function show(Contract $contract): View {
        Gate::authorize('view', $contract);

        $contract->load(['customer', 'supplier', 'document', 'responsible', 'obligations.responsible', 'attachments']);

        return view('contracts.show', [
            'contract' => $contract,
            'nextTermination' => $contract->status->isOpen() ? $this->service->nextTerminationDate($contract) : null,
            'noticeDeadline' => $contract->status->isOpen() ? $this->service->noticeDeadline($contract) : null,
            'obligationKinds' => ContractObligationKind::cases(),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'assetFinanceOptions' => AssetFinanceContract::query()->orderByDesc('id')->limit(200)->get(['id', 'number', 'partner_name']),
            'linkedAssetFinance' => AssetFinanceContract::query()->where('contract_id', $contract->id)->get(['id', 'number', 'partner_name']),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Contract::class);

        return view('contracts._form_dialog', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Contract::class);

        $actor = $request->user() ?? abort(401);
        $organization = $actor->organization ?? abort(403);

        $contract = $this->service->create($organization, $actor, $this->validated($request));

        return redirect()->route('contracts.show', $contract)
            ->with('status', __('Vertrag :number angelegt.', ['number' => $contract->number]));
    }

    public function update(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        if (! $contract->status->isOpen()) {
            return back()->withErrors(['status' => __('Beendete oder stornierte Verträge sind unveränderlich.')]);
        }

        $contract->fill($this->validated($request))->save();

        return back()->with('status', __('Vertrag aktualisiert.'));
    }

    public function activate(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        return $this->transition(fn () => $this->service->activate($contract, $request->user() ?? abort(401)), __('Vertrag aktiviert.'));
    }

    public function terminate(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->transition(fn () => $this->service->terminate($contract, $request->user() ?? abort(401), $data['reason']), __('Vertrag gekündigt.'));
    }

    public function end(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        return $this->transition(fn () => $this->service->end($contract, $request->user() ?? abort(401)), __('Vertrag beendet.'));
    }

    /** Obligation/Termin ergänzen (Vertragskalender). */
    public function storeObligation(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(ContractObligationKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
            'warn_days_before' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'recurring' => ['sometimes', 'boolean'],
            'recurrence_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($request->filled('responsible_user_id')) {
            $data['responsible_user_id'] = Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id'));
        }
        $data['recurring'] = $request->boolean('recurring');
        $data['warn_days_before'] ??= 30;

        $this->service->addObligation($contract, $data);

        return back()->with('status', __('Obligation ergänzt.'));
    }

    public function completeObligation(Request $request, ContractObligation $obligation): RedirectResponse {
        Gate::authorize('update', $obligation->contract()->firstOrFail());

        $this->service->completeObligation($obligation, $request->user() ?? abort(401));

        return back()->with('status', __('Obligation als erledigt markiert.'));
    }

    /** Additive Verknüpfung eines Leasing-/Finanzierungsvertrags (org-konsistent). */
    public function linkAssetFinance(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        $request->merge(['asset_finance_id' => Sqid::decodeOrNumeric(AssetFinanceContract::class, $request->input('asset_finance_id'))]);
        $data = $request->validate([
            'asset_finance_id' => ['required', 'integer', new ExistsInCurrentOrganization('asset_finance_contracts')],
        ]);

        $assetFinance = AssetFinanceContract::query()->whereKey($data['asset_finance_id'])->firstOrFail();

        try {
            $this->service->linkAssetFinanceContract($assetFinance, $contract);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['asset_finance_id' => $e->getMessage()]);
        }

        return back()->with('status', __('Leasingvertrag verknüpft.'));
    }

    /** @param callable():mixed $action */
    private function transition(callable $action, string $success): RedirectResponse {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        return [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'documents' => Document::query()->orderByDesc('id')->limit(200)->get(['id', 'title']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $fieldModels = [
            'customer_id' => Customer::class,
            'supplier_id' => Supplier::class,
            'document_id' => Document::class,
            'responsible_user_id' => User::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(ContractKind::class)],
            'partner_type' => ['required', Rule::enum(ContractPartnerType::class)],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'supplier_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'term_kind' => ['required', Rule::enum(ContractTermKind::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'min_term_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'auto_renew' => ['sometimes', 'boolean'],
            'renew_period_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'indexation_method' => ['required', Rule::enum(IndexationMethod::class)],
            'indexation_value' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'indexation_review_on' => ['nullable', 'date'],
            'indexation_note' => ['nullable', 'string', 'max:255'],
            'value_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(\CommonToolkit\Enums\CurrencyCode::class)],
            'value_period' => ['required', Rule::in(['once', 'monthly', 'quarterly', 'yearly'])],
            'document_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('documents')],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'notes' => ['nullable', 'string', 'max:8000'],
        ]);
        $validated['auto_renew'] = $request->boolean('auto_renew');

        return $validated;
    }
}
