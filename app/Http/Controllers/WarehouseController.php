<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveWarehouseRequest;
use App\Models\{Site, Team, Vehicle, Warehouse};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Verwaltung lokaler Lagerorte (Feature 048, MVP-067) als Modal-Dialoge;
 * seit MVP-706 mit Art und Bezug (Standort/Fahrzeug/Team).
 * Modul-Gating über `warehouses.*` → module.lager.
 */
class WarehouseController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request): View {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()
            ->withCount(['movements', 'bins'])
            ->with(['site', 'vehicle', 'team'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(25);

        return view('warehouses.index', ['warehouses' => $warehouses]);
    }

    public function create(): View {
        Gate::authorize('create', Warehouse::class);

        return view('warehouses._form_dialog', ['warehouse' => null, 'isDialog' => true] + $this->referenceOptions());
    }

    public function store(SaveWarehouseRequest $request): RedirectResponse {
        Gate::authorize('create', Warehouse::class);

        $data = $request->warehouseData();
        $data['organization_id'] = $this->currentOrganization()->id;
        $data['created_by'] = Auth::id();
        Warehouse::create($data);

        return redirect()->route('warehouses.index')->with('success', __('inventory.flash.warehouse_created'));
    }

    public function edit(Warehouse $warehouse): View {
        Gate::authorize('update', $warehouse);

        return view('warehouses._form_dialog', ['warehouse' => $warehouse, 'isDialog' => true] + $this->referenceOptions());
    }

    public function update(SaveWarehouseRequest $request, Warehouse $warehouse): RedirectResponse {
        Gate::authorize('update', $warehouse);

        $warehouse->update($request->warehouseData());

        return redirect()->route('warehouses.index')->with('success', __('inventory.flash.warehouse_updated'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse {
        Gate::authorize('delete', $warehouse);

        if ($warehouse->movements()->exists()) {
            return redirect()->route('warehouses.index')
                ->with('error', __('inventory.flash.warehouse_delete_blocked'));
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', __('inventory.flash.warehouse_deleted'));
    }

    /**
     * Auswahllisten für den Bezug (Sqid → Label), org-gescopt über die Model-Scopes.
     *
     * @return array{sites: array<string, string>, vehicles: array<string, string>, teams: array<string, string>}
     */
    private function referenceOptions(): array {
        return [
            'sites' => Site::query()->orderBy('name')->get(['id', 'name'])
                ->mapWithKeys(fn (Site $site): array => [$site->sqid => (string) $site->name])->all(),
            'vehicles' => Vehicle::query()->whereNull('archived_at')->orderBy('label')->orderBy('license_plate')->get()
                ->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->sqid => $vehicle->displayName()])->all(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name'])
                ->mapWithKeys(fn (Team $team): array => [$team->sqid => (string) $team->name])->all(),
        ];
    }
}
