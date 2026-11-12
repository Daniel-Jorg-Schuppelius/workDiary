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

namespace App\Http\Controllers\Warranty;

use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warranty\SaveWarrantyPeriodRequest;
use App\Models\Claims\ClaimCase;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Protocol\Protocol;
use App\Models\Supplier\Supplier;
use App\Models\Warranty\WarrantyPeriod;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Warranty\WarrantyService;
use App\Support\{ErrorText, Sqid};
use CommonToolkit\Helper\Data\StringHelper;
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
            // Kachel „läuft bald ab“ (ux-pattern-katalog §4): Ende innerhalb von 180 Tagen.
            'expiring' => $request->boolean('expiring'),
        ];

        $query = WarrantyPeriod::query()->with(['customer:id,name,company', 'supplier:id,name,company', 'project:id,name', 'responsible:id,name', 'claimCase:id,number,title']);
        if (WarrantySide::tryFrom($filters['side']) !== null) {
            $query->where('side', $filters['side']);
        }
        if (WarrantyStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['expiring']) {
            $query->whereBetween('ends_on', \App\Support\Query\DateRange::days(now(), now()->addDays(180)));
        }

        $critical = $this->warranties->subcontractorsEndingFirst((int) $this->currentOrganization()->id);

        return view('warranties.index', [
            'periods' => $query->orderBy('ends_on')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'critical' => $critical,
            'canOpenClaims' => $this->mayLinkClaims($request),
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
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
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
        $side = WarrantySide::from((string) $data['side']);
        $basis = WarrantyBasis::from((string) $data['basis']);
        $attributes = $this->references($data) + ['organization_id' => $this->currentOrganization()->id];

        try {
            // Ohne eigenen Beginn zählt der Abnahmetag des gewählten Protokolls.
            ($data['starts_on'] ?? null) === null
                ? $this->warranties->fromAcceptance(Protocol::query()->whereKey((int) $data['protocol_id'])->firstOrFail(), $side, $basis, $data['ends_on'] ?? null, $data['override_reason'] ?? null, $request->user(), $attributes)
                : $this->warranties->create($side, $basis, (string) $data['starts_on'], $data['ends_on'] ?? null, $data['override_reason'] ?? null, $request->user(), $attributes);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }

        return redirect()->toList('warranties.index')->with('status', __('warranty.created'));
    }

    /** Frist ändern (MVP-1564): gekoppelte Einbehalte folgen einem verschobenen Ende. */
    public function update(SaveWarrantyPeriodRequest $request, WarrantyPeriod $warranty): RedirectResponse {
        Gate::authorize('viewAny', Project::class);
        $data = $request->validated();

        try {
            $this->warranties->update(
                $warranty,
                WarrantySide::from((string) $data['side']),
                WarrantyBasis::from((string) $data['basis']),
                (string) ($data['starts_on'] ?? Protocol::query()->whereKey((int) $data['protocol_id'])->firstOrFail()->occurred_at->toDateString()),
                $data['ends_on'] ?? null,
                $data['override_reason'] ?? null,
                $this->references($data),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }

        return redirect()->toList('warranties.index')->with('status', __('warranty.updated'));
    }

    /** Dialog „Rüge erfassen" (MVP-1564); Reklamationsakten nur mit aktivem Modul und Leserecht. */
    public function claimForm(Request $request, WarrantyPeriod $warranty): View {
        Gate::authorize('viewAny', Project::class);

        return view('warranties._claim_dialog', [
            'period' => $warranty,
            'claims' => $this->mayLinkClaims($request)
                ? ClaimCase::query()->open()->orderByDesc('reported_at')->limit(200)->get(['id', 'number', 'title', 'project_id', 'customer_id'])
                : null,
        ]);
    }

    public function claim(Request $request, WarrantyPeriod $warranty): RedirectResponse {
        Gate::authorize('viewAny', Project::class);
        $data = $request->validate(['claim_case_id' => ['nullable', 'string', 'max:64']]);

        $claim = null;
        if (StringHelper::trimToNull($data['claim_case_id'] ?? null) !== null) {
            abort_unless($this->mayLinkClaims($request), 403);
            // Der Org-Scope des Modells hält fremde Akten fern.
            $claim = ClaimCase::query()->find(Sqid::decode(ClaimCase::class, (string) $data['claim_case_id']));
            if ($claim === null) {
                return back()->with('error', __('warranty.claim.unknown_case'));
            }
        }

        try {
            $this->warranties->markClaimed($warranty, $claim?->id, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('status', __('warranty.claim.recorded'));
    }

    private function mayLinkClaims(Request $request): bool {
        return app(FeatureFlagResolver::class)->routeEnabled('claims.index')
            && ($request->user()?->can('viewAny', ClaimCase::class) ?? false);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function references(array $data): array {
        return array_intersect_key($data, array_flip(['protocol_id', 'project_id', 'diary_entry_id', 'customer_id', 'supplier_id', 'trade', 'responsible_user_id', 'note']));
    }

    public function close(Request $request, WarrantyPeriod $warranty): RedirectResponse {
        Gate::authorize('viewAny', Project::class);
        $this->warranties->close($warranty, $request->user());

        return back()->with('status', __('warranty.closed'));
    }
}
