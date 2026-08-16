<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesDiscountGroupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\{Article, SalesDiscountGroup};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verwaltung der Verkaufs-Rabattgruppen (Feature 107, W9): org-weite
 * Standard-Konditionen für den DATANORM-Listenpreis-Export. Bewusst schlank —
 * eine Seite mit Liste, Anlage und Löschung; die Artikel-Zuordnung passiert
 * im Artikel-Formular.
 */
class SalesDiscountGroupController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', Article::class);

        return view('articles.sales_discount_groups', [
            'groups' => SalesDiscountGroup::query()->withCount('articles')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Article::class);
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:4', 'alpha_num:ascii',
                Rule::unique('sales_discount_groups', 'code')->where('organization_id', $this->currentOrganization()->id),
            ],
            'kind' => ['required', Rule::in([SalesDiscountGroup::KIND_DISCOUNT, SalesDiscountGroup::KIND_FACTOR, SalesDiscountGroup::KIND_SURCHARGE])],
            'value' => ['required', 'numeric', 'min:0', 'max:999'],
            'label' => ['nullable', 'string', 'max:191'],
        ]);

        SalesDiscountGroup::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'code' => strtoupper($data['code']),
            'kind' => $data['kind'],
            'value' => $data['value'],
            'label' => ($data['label'] ?? '') !== '' ? $data['label'] : null,
        ]);

        return back()->with('success', (string) __('article.discount_group.flash.created'));
    }

    public function destroy(SalesDiscountGroup $salesDiscountGroup): RedirectResponse {
        Gate::authorize('create', Article::class);
        abort_unless($salesDiscountGroup->organization_id === $this->currentOrganization()->id, 404);
        $salesDiscountGroup->delete(); // Artikel-FK ist nullOnDelete.

        return back()->with('success', (string) __('article.discount_group.flash.deleted'));
    }
}
