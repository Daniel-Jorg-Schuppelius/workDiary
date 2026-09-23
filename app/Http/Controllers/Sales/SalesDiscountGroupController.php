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
            // MVP-567: kundenindividuelle Overrides je Gruppe.
            'overrides' => \App\Models\SalesDiscountGroupOverride::query()->with(['group', 'customer'])->orderBy('sales_discount_group_id')->get(),
            'customers' => \App\Models\Customer::query()->orderBy('name')->get(['id', 'name', 'company', 'number']),
        ]);
    }

    /** MVP-567: Kunden-Override anlegen/aktualisieren. */
    public function storeOverride(Request $request): RedirectResponse {
        Gate::authorize('create', Article::class);
        $organizationId = $this->currentOrganization()->id;

        // Sqids aus dem Formular (I7); rohe numerische IDs bleiben als Fallback lesbar.
        $request->merge([
            'sales_discount_group_id' => \App\Support\Sqid::decodeOrNumeric(SalesDiscountGroup::class, $request->input('sales_discount_group_id')),
            'customer_id' => \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer::class, $request->input('customer_id')),
        ]);

        $data = $request->validate([
            'sales_discount_group_id' => ['required', 'integer', Rule::exists('sales_discount_groups', 'id')->where('organization_id', $organizationId)],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('organization_id', $organizationId)],
            'kind' => ['required', Rule::in([SalesDiscountGroup::KIND_DISCOUNT, SalesDiscountGroup::KIND_FACTOR, SalesDiscountGroup::KIND_SURCHARGE])],
            'value' => ['required', 'numeric', 'min:0', 'max:999'],
        ]);

        \App\Models\SalesDiscountGroupOverride::query()->updateOrCreate(
            [
                'sales_discount_group_id' => (int) $data['sales_discount_group_id'],
                'customer_id' => (int) $data['customer_id'],
            ],
            ['organization_id' => $organizationId, 'kind' => $data['kind'], 'value' => $data['value']]
        );

        return back()->with('success', (string) __('article.discount_group.flash.override_saved'));
    }

    public function destroyOverride(\App\Models\SalesDiscountGroupOverride $override): RedirectResponse {
        Gate::authorize('create', Article::class);
        abort_unless($override->organization_id === $this->currentOrganization()->id, 404);
        $override->delete();

        return back()->with('success', (string) __('article.discount_group.flash.override_deleted'));
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
