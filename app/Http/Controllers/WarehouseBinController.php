<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseBinController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveWarehouseBinRequest;
use App\Models\{Warehouse, WarehouseBin};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Lagerplätze je Lagerort (Feature 048, MVP-706) als Modal-Dialoge. Rechte
 * laufen über die WarehousePolicy (sehen: inventory.viewAny, verwalten:
 * inventory.configure); ein Platz wird nur im Kontext seines Lagers bedient.
 */
class WarehouseBinController extends Controller {
    public function index(Warehouse $warehouse): View {
        Gate::authorize('view', $warehouse);

        $bins = $warehouse->bins()->withCount('movements')->get();

        return view('warehouses.bins.index', ['warehouse' => $warehouse, 'bins' => $bins]);
    }

    public function create(Warehouse $warehouse): View {
        Gate::authorize('update', $warehouse);

        return view('warehouses.bins._form_dialog', ['warehouse' => $warehouse, 'bin' => null, 'isDialog' => true]);
    }

    public function store(SaveWarehouseBinRequest $request, Warehouse $warehouse): RedirectResponse {
        Gate::authorize('update', $warehouse);

        $data = $request->binData();
        $data['organization_id'] = $warehouse->organization_id;
        $warehouse->bins()->create($data);

        return redirect()->route('warehouses.bins.index', $warehouse)->with('success', __('inventory.flash.bin_created'));
    }

    public function edit(Warehouse $warehouse, WarehouseBin $bin): View {
        Gate::authorize('update', $warehouse);
        $this->assertBelongs($warehouse, $bin);

        return view('warehouses.bins._form_dialog', ['warehouse' => $warehouse, 'bin' => $bin, 'isDialog' => true]);
    }

    public function update(SaveWarehouseBinRequest $request, Warehouse $warehouse, WarehouseBin $bin): RedirectResponse {
        Gate::authorize('update', $warehouse);
        $this->assertBelongs($warehouse, $bin);

        $bin->update($request->binData());

        return redirect()->route('warehouses.bins.index', $warehouse)->with('success', __('inventory.flash.bin_updated'));
    }

    /** Sperre umschalten (gesperrt ⇄ frei) — Bestand bleibt, Zu-/Abgänge sind blockiert. */
    public function toggleBlock(Warehouse $warehouse, WarehouseBin $bin): RedirectResponse {
        Gate::authorize('update', $warehouse);
        $this->assertBelongs($warehouse, $bin);

        $bin->update(['blocked' => ! $bin->blocked]);

        return redirect()->route('warehouses.bins.index', $warehouse)
            ->with('success', __($bin->blocked ? 'inventory.flash.bin_blocked' : 'inventory.flash.bin_unblocked'));
    }

    public function destroy(Warehouse $warehouse, WarehouseBin $bin): RedirectResponse {
        Gate::authorize('update', $warehouse);
        $this->assertBelongs($warehouse, $bin);

        // Ledger-Nachweis: der FK ist RESTRICT — hier fachlich vorab abfangen.
        if ($bin->movements()->exists()) {
            return redirect()->route('warehouses.bins.index', $warehouse)->with('error', __('inventory.flash.bin_delete_blocked'));
        }

        $bin->delete();

        return redirect()->route('warehouses.bins.index', $warehouse)->with('success', __('inventory.flash.bin_deleted'));
    }

    private function assertBelongs(Warehouse $warehouse, WarehouseBin $bin): void {
        abort_unless($bin->warehouse_id === $warehouse->id, 404);
    }
}
