<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Building, Site};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BuildingController extends Controller {
    private const ALLOWED_SORTS = ['name', 'code', 'year_built', 'gross_area_m2'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', Building::class);

        $rawSite = (string) $request->query('site', '');
        $siteId = Sqid::decodeOrNumeric(Site::class, $rawSite);
        $search = $request->string('q')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'name';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $query = Building::query()
            ->with('site.customer')
            ->withCount('floors')
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('site', fn($s) => $s->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy($sort, $dir);
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }
        $buildings = $query->paginate(50)->withQueryString();
        $site = $siteId !== null
            ? Site::query()->find($siteId)
            : null;

        return view('buildings.index', [
            'buildings' => $buildings,
            'site' => $site,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Building::class);

        return view('buildings._form_dialog', [
            'building' => null,
            'sites' => Site::query()->with('customer')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Building::class);
        $building = Building::create($this->validateBuilding($request));

        return redirect()->route('buildings.show', $building)->with('success', __('Gebäude angelegt.'));
    }

    public function show(Building $building): View {
        Gate::authorize('view', $building);

        $building->load(['site.customer']);
        $floors = $building->floors()
            ->withCount('rooms')
            ->withSum('rooms as net_area_sum', 'net_area_m2')
            ->orderBy('level')
            ->get();

        $kpis = [
            'floors'     => $floors->count(),
            'rooms'      => (int) $floors->sum('rooms_count'),
            'gross_area' => (float) ($building->gross_area_m2 ?? $floors->sum('gross_area_m2')),
            'net_area'   => (float) $floors->sum('net_area_sum'),
        ];

        return view('buildings.show', [
            'building' => $building,
            'floors' => $floors,
            'kpis' => $kpis,
        ]);
    }

    public function edit(Building $building): View {
        Gate::authorize('update', $building);

        return view('buildings._form_dialog', [
            'building' => $building,
            'sites' => Site::query()->with('customer')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Building $building): RedirectResponse {
        Gate::authorize('update', $building);
        $building->update($this->validateBuilding($request));

        return redirect()->route('buildings.show', $building)->with('success', __('Gebäude aktualisiert.'));
    }

    public function destroy(Building $building): RedirectResponse {
        Gate::authorize('delete', $building);
        $building->delete();

        return redirect()->route('buildings.index')->with('success', __('Gebäude gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateBuilding(Request $request): array {
        $rawSiteId = $request->input('site_id');
        $siteId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Site::class, $rawSiteId);

        $request->merge([
            'site_id' => $siteId,
        ]);

        return $request->validate([
            'site_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('sites')],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:32'],
            'gross_area_m2' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:2999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
