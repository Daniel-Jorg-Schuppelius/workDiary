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

use App\Http\Controllers\Concerns\ParsesIndexQuery;
use App\Models\{Building, Floor};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller {
    use ParsesIndexQuery;

    private const ALLOWED_SORTS = ['level', 'label', 'gross_area_m2'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', Floor::class);

        $rawBuilding = (string) $request->query('building', '');
        $buildingId = Sqid::decodeOrNumeric(Building::class, $rawBuilding);
        ['search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'level');

        $query = Floor::query()
            ->with('building.site')
            ->withCount('rooms')
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->whereLikeEscaped('label', $search)
                    ->orWhereLikeEscaped('notes', $search)
                    ->orWhereHas('building', fn($b) => $b->whereLikeEscaped('name', $search));
            }))
            ->orderBy($sort, $dir);
        if ($buildingId !== null) {
            $query->where('building_id', $buildingId);
        }
        $floors = $query->paginate(100)->withQueryString();
        $building = $buildingId !== null
            ? Building::query()->find($buildingId)
            : null;

        return view('floors.index', [
            'floors' => $floors,
            'building' => $building,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
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
            ->withCount('assets')
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
        $rawBuildingId = $request->input('building_id');
        $buildingId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Building::class, $rawBuildingId);

        $request->merge([
            'building_id' => $buildingId,
        ]);

        $buildingId = (int) $request->input('building_id');
        $uniqueLevel = Rule::unique('floors', 'level')
            ->where(fn($q) => $q->where('building_id', $buildingId));
        if ($existing !== null) {
            $uniqueLevel = $uniqueLevel->ignore($existing->id);
        }

        return $request->validate([
            'building_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('buildings')],
            'level' => ['required', 'integer', 'min:-10', 'max:200', $uniqueLevel],
            'label' => ['required', 'string', 'max:80'],
            'gross_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
