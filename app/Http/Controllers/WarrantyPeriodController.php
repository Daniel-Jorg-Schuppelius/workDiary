<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyPeriodController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveWarrantyPeriodRequest;
use App\Models\{Customer, Project, Protocol, Supplier, User};
use App\Models\Warranty\WarrantyPeriod;
use App\Services\Warranty\WarrantyService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Gewährleistungsfristen (Feature 115, MVP-604).
 *
 * Die Liste zeigt beide Seiten nebeneinander — eigene Haftung und
 * einforderbare Sub-Fristen. Genau dieses Nebeneinander ist der Zweck: Erst
 * daraus wird sichtbar, welche Sub-Frist vor der eigenen endet.
 */
class WarrantyPeriodController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly WarrantyService $warranties) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Project::class);

        $filters = [
            'side' => (string) $request->query('side', ''),
            'status' => (string) $request->query('status', WarrantyStatus::Open->value),
        ];

        $query = WarrantyPeriod::query()->with(['customer:id,name,company', 'supplier:id,name,company', 'project:id,name', 'responsible:id,name']);
        if (WarrantySide::tryFrom($filters['side']) !== null) {
            $query->where('side', $filters['side']);
        }
        if (WarrantyStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        $critical = $this->warranties->subcontractorsEndingFirst((int) $this->currentOrganization()->id);

        return view('warranties.index', [
            'periods' => $query->orderBy('ends_on')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'critical' => $critical,
            'openOwed' => WarrantyPeriod::query()->where('status', WarrantyStatus::Open->value)->where('side', WarrantySide::Owed->value)->count(),
            'openClaimable' => WarrantyPeriod::query()->where('status', WarrantyStatus::Open->value)->where('side', WarrantySide::Claimable->value)->count(),
            'expiringSoon' => WarrantyPeriod::query()->where('status', WarrantyStatus::Open->value)
                ->whereDate('ends_on', '>=', now()->toDateString())
                ->whereDate('ends_on', '<=', now()->addDays(180)->toDateString())->count(),
        ]);
    }

    public function form(Request $request, ?WarrantyPeriod $warranty = null): View {
        Gate::authorize('viewAny', Project::class);
        $organizationId = $this->currentOrganization()->id;

        return view('warranties._form_dialog', [
            'period' => $warranty,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'company']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'company']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'protocols' => Protocol::query()->where('type', \App\Enums\Protocol\ProtocolType::Acceptance->value)
                ->orderByDesc('occurred_at')->limit(200)->get(['id', 'title', 'occurred_at']),
            // Nutzerliste ausdrücklich org-gefiltert: User trägt keinen
            // globalen Org-Scope (Login/2FA laufen vor dem Org-Kontext).
            'users' => User::query()->where('organization_id', $organizationId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveWarrantyPeriodRequest $request): RedirectResponse {
        Gate::authorize('viewAny', Project::class);
        $data = $request->validated();

        try {
            $this->warranties->create(
                WarrantySide::from((string) $data['side']),
                WarrantyBasis::from((string) $data['basis']),
                (string) $data['starts_on'],
                $data['ends_on'] ?? null,
                $data['override_reason'] ?? null,
                $request->user(),
                array_intersect_key($data, array_flip(['protocol_id', 'project_id', 'diary_entry_id', 'customer_id', 'supplier_id', 'trade', 'responsible_user_id', 'note']))
                    + ['organization_id' => $this->currentOrganization()->id],
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warranties.index')->with('status', __('warranty.created'));
    }

    public function close(Request $request, WarrantyPeriod $warranty): RedirectResponse {
        Gate::authorize('viewAny', Project::class);
        $this->warranties->close($warranty, $request->user());

        return back()->with('status', __('warranty.closed'));
    }
}
