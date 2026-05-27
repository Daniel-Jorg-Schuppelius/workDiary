<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FloorController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Building, Floor};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Floor::class);

        $buildingId = $request->query('building');
        $query = Floor::query()
            ->with('building.site')
            ->withCount('rooms')
            ->orderBy('building_id')
            ->orderBy('level');
        if ($buildingId !== null && $buildingId !== '') {
            $query->where('building_id', (int) $buildingId);
        }
        $floors = $query->paginate(100)->withQueryString();
        $building = $buildingId !== null && $buildingId !== ''
            ? Building::query()->find((int) $buildingId)
            : null;

        return view('floors.index', [
            'floors' => $floors,
            'building' => $building,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Floor::class);

        return view('floors._form_dialog', [
            'floor' => null,
            'buildings' => Building::query()->with('site')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Floor::class);
        $floor = Floor::create($this->validateFloor($request));

        return redirect()->route('floors.show', $floor)->with('success', __('Geschoss angelegt.'));
    }

    public function show(Floor $floor): View {
        Gate::authorize('view', $floor);

        $floor->load(['building.site.customer']);
        $rooms = $floor->rooms()
            ->with(['customer', 'cleaningProfile'])
            ->orderBy('name')
            ->get();

        $kpis = [
            'rooms'        => $rooms->count(),
            'active_rooms' => $rooms->where('is_active', true)->count(),
            'net_area'     => (float) $rooms->sum('net_area_m2'),
            'capacity'     => (int) $rooms->sum('capacity'),
        ];

        return view('floors.show', [
            'floor' => $floor,
            'rooms' => $rooms,
            'kpis' => $kpis,
        ]);
    }

    public function edit(Floor $floor): View {
        Gate::authorize('update', $floor);

        return view('floors._form_dialog', [
            'floor' => $floor,
            'buildings' => Building::query()->with('site')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Floor $floor): RedirectResponse {
        Gate::authorize('update', $floor);
        $floor->update($this->validateFloor($request, $floor));

        return redirect()->route('floors.show', $floor)->with('success', __('Geschoss aktualisiert.'));
    }

    public function destroy(Floor $floor): RedirectResponse {
        Gate::authorize('delete', $floor);
        $floor->delete();

        return redirect()->route('floors.index')->with('success', __('Geschoss gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateFloor(Request $request, ?Floor $existing = null): array {
        $buildingId = (int) $request->input('building_id');
        $uniqueLevel = Rule::unique('floors', 'level')
            ->where(fn ($q) => $q->where('building_id', $buildingId));
        if ($existing !== null) {
            $uniqueLevel = $uniqueLevel->ignore($existing->id);
        }

        return $request->validate([
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'level' => ['required', 'integer', 'min:-10', 'max:200', $uniqueLevel],
            'label' => ['required', 'string', 'max:80'],
            'gross_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
