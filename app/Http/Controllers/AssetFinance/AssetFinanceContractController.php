<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceContractController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetFinance;

use App\Enums\AssetFinance\{AssetFinanceKind, AssetFinanceStatus, AssetFinanceTermKind};
use App\Http\Controllers\Controller;
use App\Models\{Asset, CostCenter, Project, Supplier, User};
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Investments\{InvestmentCase, InvestmentLink};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\AssetFinance\AssetFinanceService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Leasing-/Finanzierungsakten (Feature 074, MVP-271/272/274): Bestand mit
 * Restlaufzeiten, Akte mit Konditionen (finance-Recht!), Verknüpfungen zu
 * Investition/Bestellung/Asset und Statuslebenszyklus.
 */
class AssetFinanceContractController extends Controller {
    public function __construct(private readonly AssetFinanceService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', AssetFinanceContract::class);

        $statusFilter = AssetFinanceStatus::tryFrom($request->string('status')->toString())?->value;
        $kindFilter = AssetFinanceKind::tryFrom($request->string('kind')->toString())?->value;

        return view('asset-finance.index', [
            'contracts' => AssetFinanceContract::query()
                ->with(['responsible', 'contractAssets.asset', 'supplier'])
                ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
                ->when($kindFilter !== null, fn($q) => $q->where('kind', $kindFilter))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'openCount' => AssetFinanceContract::query()->open()->count(),
            'endingSoonCount' => AssetFinanceContract::query()->open()
                ->whereNotNull('ends_on')
                ->whereDate('ends_on', '<=', now()->addMonths(6)->toDateString())
                ->count(),
        ]);
    }

    public function show(AssetFinanceContract $contract): View {
        Gate::authorize('view', $contract);

        $contract->load([
            'supplier', 'costCenter', 'project', 'purchaseOrder', 'responsible',
            'contractAssets.asset', 'terms', 'rateSchedules.incomingEInvoice',
            'deadlines.responsible', 'usageLimits', 'options', 'endProcesses.decider',
            'costSnapshots', 'attachments',
        ]);

        $canFinance = Gate::allows('finance', $contract);

        return view('asset-finance.show', [
            'contract' => $contract,
            'canFinance' => $canFinance,
            'projection' => $canFinance ? $this->service->projection($contract) : null,
            'investmentLink' => InvestmentLink::query()
                ->where('linkable_type', $contract->getMorphClass())
                ->where('linkable_id', $contract->id)
                ->with('investmentCase')
                ->first(),
            'termKinds' => AssetFinanceTermKind::cases(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Anlege-Dialog (Formulare in Dialogen). */
    public function create(): View {
        Gate::authorize('create', AssetFinanceContract::class);

        return view('asset-finance._form_dialog', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'costCenters' => CostCenter::query()->where('active', true)->orderBy('code')->get(['id', 'code', 'label']),
            'investmentCases' => InvestmentCase::query()->orderByDesc('id')->limit(100)->get(['id', 'title']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', AssetFinanceContract::class);

        $actor = $request->user() ?? abort(401);
        $organization = $actor->organization ?? abort(403);
        $data = $this->validated($request);

        $assetIds = array_values(array_map('intval', (array) ($data['asset_ids'] ?? [])));
        $investmentCaseId = $data['investment_case_id'] ?? null;
        unset($data['asset_ids'], $data['investment_case_id']);

        $contract = $this->service->create($organization, $actor, $data, $assetIds);

        // MVP-274: Verknüpfung zur Investitionsplanung — die Investition
        // bleibt führend für Budget/Freigabe (Datenführerschaft).
        if ($investmentCaseId !== null) {
            InvestmentLink::query()->create([
                'organization_id' => $organization->id,
                'investment_case_id' => (int) $investmentCaseId,
                'linkable_type' => $contract->getMorphClass(),
                'linkable_id' => $contract->id,
                'note' => (string) __('Leasing-/Finanzierungsakte'),
                'created_by' => $actor->id,
            ]);
        }

        return redirect()->route('asset-finance.show', $contract)
            ->with('status', __('Leasingakte :number angelegt.', ['number' => $contract->number]));
    }

    public function update(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        if (! $contract->status->isOpen()) {
            return back()->withErrors(['status' => __('Beendete oder stornierte Akten sind unveränderlich.')]);
        }

        $data = $this->validated($request);
        unset($data['asset_ids'], $data['investment_case_id']);

        // Konditionen (Beträge) nur mit finance-Recht ändern.
        if (! Gate::allows('finance', $contract)) {
            unset($data['rate_amount'], $data['special_payment'], $data['residual_value'], $data['purchase_option_amount']);
        }

        $contract->fill($data)->save();

        return back()->with('status', __('Leasingakte aktualisiert.'));
    }

    public function activate(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('finance', $contract);

        try {
            $this->service->activate($contract, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Vertrag aktiviert — Konditionen eingefroren, Ratenplan erzeugt.'));
    }

    public function terminate(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        try {
            $this->service->terminate($contract, $request->user() ?? abort(401), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Vertrag gekündigt.'));
    }

    public function close(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('update', $contract);

        try {
            $this->service->close($contract, $request->user() ?? abort(401));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', __('Akte abgeschlossen.'));
    }

    /** Strukturierte Kondition ergänzen (MVP-272, finance-Recht). */
    public function storeTerm(Request $request, AssetFinanceContract $contract): RedirectResponse {
        Gate::authorize('finance', $contract);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(AssetFinanceTermKind::class)],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract->terms()->create(array_merge($data, ['organization_id' => $contract->organization_id]));

        return back()->with('status', __('Kondition ergänzt.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $fieldModels = [
            'supplier_id' => Supplier::class,
            'cost_center_id' => CostCenter::class,
            'project_id' => Project::class,
            'purchase_order_id' => \App\Models\PurchaseOrder::class,
            'responsible_user_id' => User::class,
            'investment_case_id' => InvestmentCase::class,
        ];
        foreach ($fieldModels as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }
        if ($request->filled('asset_ids')) {
            $request->merge([
                'asset_ids' => array_map(
                    fn($value) => Sqid::decodeOrNumeric(Asset::class, $value),
                    (array) $request->input('asset_ids'),
                ),
            ]);
        }

        return $request->validate([
            'kind' => ['required', Rule::enum(AssetFinanceKind::class)],
            'partner_name' => ['required', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'contract_no' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'payment_rhythm' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'rate_amount' => ['nullable', 'numeric', 'min:0'],
            'special_payment' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'purchase_option_amount' => ['nullable', 'numeric', 'min:0'],
            'cost_center_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('cost_centers')],
            'cost_center_label' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'purchase_order_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('purchase_orders')],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'investment_case_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('investment_cases')],
            'insurance_note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:8000'],
            'asset_ids' => ['sometimes', 'array'],
            'asset_ids.*' => ['integer', new ExistsInCurrentOrganization('assets')],
        ]);
    }
}
