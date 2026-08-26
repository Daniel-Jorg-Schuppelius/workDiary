<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\AssetCompliance\AssetComplianceStatus;
use App\Enums\Vehicle\{VehicleOwnership, VehiclePropulsion, VehicleType};
use App\Http\Requests\SaveVehicleRequest;
use App\Models\{Asset, User, Vehicle};
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\Fleet\VehicleService;
use App\Support\{Setting, SortableQuery};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class VehicleController extends Controller {
    /** Ampel-Farben je Prüfstatus (Feature 138). */
    private const INSPECTION_TONES = [
        AssetComplianceStatus::Valid->value => 'success',
        AssetComplianceStatus::DueSoon->value => 'warning',
        AssetComplianceStatus::Overdue->value => 'error',
        AssetComplianceStatus::Restricted->value => 'warning',
        AssetComplianceStatus::Blocked->value => 'error',
        AssetComplianceStatus::NotApplicable->value => 'ghost',
    ];

    public function __construct(
        private readonly VehicleService $service,
        private readonly AssetComplianceService $compliance,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Vehicle::class);

        $showArchived = $request->boolean('archived');

        $query = Vehicle::query()->with(['asset.complianceAssignments' => fn ($q) => $q->active()->with('profile')->orderBy('next_due_on')]);
        if (! $showArchived) {
            $query->whereNull('archived_at');
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'license_plate' => 'license_plate',
            'label' => 'label',
            'type' => 'vehicle_type',
            'propulsion' => 'propulsion',
            'rate' => 'default_rate_per_km',
            'odometer' => 'odometer_km',
        ], 'label', 'asc');

        $vehicles = $query->paginate((int) Setting::get('pagination.vehicles', 25))->withQueryString();

        // Fristen-Ampel (Feature 138): Prüfstatus des zugeordneten Assets + nächste Pflicht.
        $inspections = [];
        foreach ($vehicles as $vehicle) {
            $asset = $vehicle->asset;
            if (! $asset instanceof Asset) {
                continue;
            }
            $next = $asset->complianceAssignments->first();
            $inspections[$vehicle->id] = [
                'status' => $this->compliance->statusFor($asset),
                'next_profile' => $next?->profile?->name,
                'next_due_on' => $next?->next_due_on,
            ];
        }

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'inspections' => $inspections,
            'inspectionTones' => self::INSPECTION_TONES,
            'showArchived' => $showArchived,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Vehicle::class);

        /** @var User $auth */
        $auth = Auth::user();

        return view('vehicles._form_dialog', [
            'vehicle' => null,
            // Nur Nutzer der eigenen Organisation (kein globaler OrganizationScope
            // auf User — Whitebox-Befund 2026-07).
            'users' => User::query()->where('organization_id', $auth->organization_id)->orderBy('name')->get(['id', 'name']),
            'assets' => $this->assetOptions(),
            'types' => VehicleType::cases(),
            'propulsions' => VehiclePropulsion::cases(),
            'ownerships' => VehicleOwnership::cases(),
        ]);
    }

    public function store(SaveVehicleRequest $request): RedirectResponse {
        Gate::authorize('create', Vehicle::class);

        $data = $request->validated();
        /** @var User $auth */
        $auth = Auth::user();
        $data['organization_id'] = $auth->organization_id;

        $vehicle = $this->service->create($data);

        return redirect()->route('vehicles.index')
            ->with('success', __('Fahrzeug erfasst: :label.', ['label' => $vehicle->displayName()]));
    }

    public function edit(Vehicle $vehicle): View {
        Gate::authorize('update', $vehicle);

        /** @var User $auth */
        $auth = Auth::user();

        return view('vehicles._form_dialog', [
            'vehicle' => $vehicle,
            'users' => User::query()->where('organization_id', $auth->organization_id)->orderBy('name')->get(['id', 'name']),
            'assets' => $this->assetOptions(),
            'types' => VehicleType::cases(),
            'propulsions' => VehiclePropulsion::cases(),
            'ownerships' => VehicleOwnership::cases(),
        ]);
    }

    public function update(SaveVehicleRequest $request, Vehicle $vehicle): RedirectResponse {
        Gate::authorize('update', $vehicle);

        $this->service->update($vehicle, $request->validated());

        return redirect()->route('vehicles.index')
            ->with('success', __('Fahrzeug aktualisiert.'));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse {
        Gate::authorize('delete', $vehicle);

        $this->service->archive($vehicle);

        return redirect()->route('vehicles.index')
            ->with('success', __('Fahrzeug archiviert.'));
    }

    public function restore(Vehicle $vehicle): RedirectResponse {
        Gate::authorize('update', $vehicle);

        $this->service->restore($vehicle);

        return redirect()->route('vehicles.index', ['archived' => 1])
            ->with('success', __('Fahrzeug reaktiviert.'));
    }

    /**
     * Asset-Picker (Feature 138): org-gescopt (OrganizationScope), Fahrzeug-Assets zuerst.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Asset>
     */
    private function assetOptions(): \Illuminate\Database\Eloquent\Collection {
        return Asset::query()
            ->orderByRaw("CASE WHEN asset_class = 'vehicle' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'asset_no', 'asset_class']);
    }
}
