<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SiteController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Customer, Floor, Room, Site};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Site::class);

        $rawCustomer = (string) $request->query('customer', '');
        $customerId = Sqid::decodeOrNumeric(Customer::class, $rawCustomer);
        $query = Site::query()
            ->with('customer')
            ->withCount('buildings')
            ->orderBy('name');
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }
        $sites = $query->paginate(50)->withQueryString();
        $customer = $customerId !== null
            ? Customer::query()->find($customerId)
            : null;

        return view('sites.index', [
            'sites' => $sites,
            'customer' => $customer,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Site::class);

        return view('sites._form_dialog', [
            'site' => null,
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Site::class);
        $site = Site::create($this->validateSite($request));

        return redirect()->route('sites.show', $site)->with('success', __('Standort angelegt.'));
    }

    public function show(Site $site): View {
        Gate::authorize('view', $site);

        $site->load(['customer']);
        $buildings = $site->buildings()
            ->withCount('floors')
            ->orderBy('name')
            ->get();

        $buildingIds = $buildings->pluck('id');
        $floorIds = Floor::query()->whereIn('building_id', $buildingIds)->pluck('id');

        // Räume pro Gebäude über die Floor-Beziehung aggregieren.
        $roomsPerBuilding = $floorIds->isEmpty()
            ? collect()
            : Room::query()
            ->selectRaw('floors.building_id as building_id, count(rooms.id) as rooms_count')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->whereIn('rooms.floor_id', $floorIds)
            ->groupBy('floors.building_id')
            ->pluck('rooms_count', 'building_id');

        foreach ($buildings as $b) {
            $b->setAttribute('rooms_count', (int) ($roomsPerBuilding[$b->id] ?? 0));
        }

        $kpis = [
            'buildings'  => $buildings->count(),
            'floors'     => (int) $floorIds->count(),
            'rooms'      => (int) $roomsPerBuilding->sum(),
            'gross_area' => (float) $buildings->sum('gross_area_m2'),
        ];

        return view('sites.show', [
            'site' => $site,
            'buildings' => $buildings,
            'kpis' => $kpis,
        ]);
    }

    public function edit(Site $site): View {
        Gate::authorize('update', $site);

        return view('sites._form_dialog', [
            'site' => $site,
            'customers' => Customer::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse {
        Gate::authorize('update', $site);
        $site->update($this->validateSite($request));

        return redirect()->route('sites.show', $site)->with('success', __('Standort aktualisiert.'));
    }

    public function destroy(Site $site): RedirectResponse {
        Gate::authorize('delete', $site);
        $site->delete();

        return redirect()->route('sites.index')->with('success', __('Standort gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateSite(Request $request): array {
        $rawCustomerId = $request->input('customer_id');
        $customerId = \App\Support\Sqid::decode(\App\Models\Customer::class, $rawCustomerId);
        if ($customerId === null && is_numeric($rawCustomerId)) {
            $customerId = (int) $rawCustomerId;
        }

        $request->merge([
            'customer_id' => $customerId,
        ]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:32'],
            'address_street' => ['nullable', 'string', 'max:160'],
            'address_zip' => ['nullable', 'string', 'max:16'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:2'],
            'geo_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'geo_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_active'] ??= false;

        return $data;
    }
}
