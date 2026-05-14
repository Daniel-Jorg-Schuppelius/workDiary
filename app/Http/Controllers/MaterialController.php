<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMaterialRequest;
use App\Models\Material;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MaterialController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Material::class);
        $q = $request->string('q')->toString();

        $materials = Material::query()
            ->when($q !== '', fn($builder) => $builder->where('name', 'like', '%' . $q . '%'));

        [$sort, $dir] = SortableQuery::apply($materials, $request, [
            'sku' => 'sku',
            'name' => 'name',
            'unit' => 'unit',
            'price' => 'default_unit_price',
            'tax' => 'tax_rate',
            'provider' => 'external_provider',
        ], 'name', 'asc');

        $materials = $materials->paginate(30)->withQueryString();

        return view('materials.index', compact('materials', 'q', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', Material::class);

        return view('materials.form', ['material' => new Material(['unit' => 'Stk.', 'is_active' => true])]);
    }

    public function store(SaveMaterialRequest $request): RedirectResponse {
        Gate::authorize('create', Material::class);
        Material::create($request->validated());

        return redirect()->route('materials.index')->with('success', __('Material angelegt.'));
    }

    public function edit(Material $material): View {
        Gate::authorize('update', $material);

        return view('materials.form', compact('material'));
    }

    public function update(Material $material, SaveMaterialRequest $request): RedirectResponse {
        Gate::authorize('update', $material);
        $material->update($request->validated());

        return redirect()->route('materials.index')->with('success', __('Material aktualisiert.'));
    }

    public function destroy(Material $material): RedirectResponse {
        Gate::authorize('delete', $material);
        $material->delete();

        return redirect()->route('materials.index')->with('success', __('Material gelöscht.'));
    }
}
