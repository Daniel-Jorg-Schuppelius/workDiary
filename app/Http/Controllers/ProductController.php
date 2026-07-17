<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Product\ProductStatus;
use App\Http\Requests\Product\SaveProductRequest;
use App\Models\{Classification, Product};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Produktstamm (Typ-Ebene Hersteller-Modell, MVP-370): Liste mit Suche und
 * Modal-Pflege nach UI-Konvention; Artikel/Assets verweisen über den
 * Product-Picker auf den Typ (produktmodell-konzept.md).
 */
class ProductController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Product::class);

        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->withCount(['articles', 'assets'])
            ->when($search !== '', fn($q) => $q->search($search))
            ->orderBy('manufacturer')
            ->orderBy('model')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'canManage' => Gate::allows('create', Product::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Product::class);

        return view('products._form_dialog', [
            'product' => new Product(['status' => ProductStatus::Active]),
            'productGroups' => $this->productGroupOptions(),
        ]);
    }

    public function store(SaveProductRequest $request): RedirectResponse {
        Gate::authorize('create', Product::class);

        Product::query()->create($request->validated());

        return redirect()->route('products.index')
            ->with('success', __('products.flash.created'));
    }

    public function edit(Product $product): View {
        Gate::authorize('update', $product);

        return view('products._form_dialog', [
            'product' => $product,
            'productGroups' => $this->productGroupOptions(),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse {
        Gate::authorize('update', $product);

        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('success', __('products.flash.updated'));
    }

    public function destroy(Product $product): RedirectResponse {
        Gate::authorize('delete', $product);

        // FKs stehen auf nullOnDelete — Artikel/Assets bleiben, verlieren nur den Typ.
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', __('products.flash.deleted'));
    }

    /**
     * Produktgruppen-Klassifikationen (Domäne product_group) inkl.
     * Plattform-Defaults als Auswahloptionen.
     *
     * @return \Illuminate\Support\Collection<int, Classification>
     */
    private function productGroupOptions(): \Illuminate\Support\Collection {
        return Classification::query()
            ->where('domain', ClassificationDomain::ProductGroup->value)
            ->orderBy('label')
            ->get();
    }
}
