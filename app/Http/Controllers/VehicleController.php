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

use App\Enums\Vehicle\VehicleOwnership;
use App\Enums\Vehicle\VehiclePropulsion;
use App\Enums\Vehicle\VehicleType;
use App\Http\Requests\SaveVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fleet\VehicleService;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $service) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);

        $showArchived = $request->boolean('archived');

        $query = Vehicle::query();
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

        return view('vehicles.index', [
            'vehicles' => $query->paginate((int) setting('pagination.vehicles', 25))->withQueryString(),
            'showArchived' => $showArchived,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('vehicles._form_dialog', [
            'vehicle' => null,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'types' => VehicleType::cases(),
            'propulsions' => VehiclePropulsion::cases(),
            'ownerships' => VehicleOwnership::cases(),
        ]);
    }

    public function store(SaveVehicleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);

        $data = $request->validated();
        /** @var User $auth */
        $auth = Auth::user();
        $data['organization_id'] = $auth->organization_id;

        $vehicle = $this->service->create($data);

        return redirect()->route('vehicles.index')
            ->with('success', __('Fahrzeug erfasst: :label.', ['label' => $vehicle->displayName()]));
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('vehicles._form_dialog', [
            'vehicle' => $vehicle,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'types' => VehicleType::cases(),
            'propulsions' => VehiclePropulsion::cases(),
            'ownerships' => VehicleOwnership::cases(),
        ]);
    }

    public function update(SaveVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $this->service->update($vehicle, $request->validated());

        return redirect()->route('vehicles.index')
            ->with('success', __('Fahrzeug aktualisiert.'));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        $this->service->archive($vehicle);

        return redirect()->route('vehicles.index')
            ->with('success', __('Fahrzeug archiviert.'));
    }

    public function restore(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $this->service->restore($vehicle);

        return redirect()->route('vehicles.index', ['archived' => 1])
            ->with('success', __('Fahrzeug reaktiviert.'));
    }
}
