<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardCatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Safety;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Safety\{HazardAssessment, HazardCatalogItem};
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Gefährdungskatalog der Organisation (Feature 132, MVP-1002): deaktivieren statt löschen. */
class HazardCatalogController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request): View {
        Gate::authorize('viewAny', HazardAssessment::class);

        // Standard wie bisher: aktive Einträge zuerst, darin nach Kategorie und Gefährdung.
        [$sort, $dir] = SortableQuery::resolve($request, ['category', 'hazard', 'risk', 'source', 'is_active'], 'is_active', 'desc');
        $query = HazardCatalogItem::query()->where('organization_id', $this->currentOrganizationId());
        match ($sort) {
            'risk' => $query->orderByRaw($dir === 'asc' ? 'severity * likelihood asc' : 'severity * likelihood desc'),
            'source' => $query->orderBy('source_profile', $dir),
            default => $query->orderBy($sort, $dir),
        };

        return view('safety.hazard-catalog.index', [
            'items' => $query->orderBy('category')->orderBy('hazard')->orderBy('id')->paginate(30)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'canManage' => Gate::allows('create', HazardAssessment::class),
        ]);
    }

    public function form(?HazardCatalogItem $hazardCatalogItem = null): View {
        Gate::authorize('create', HazardAssessment::class);
        abort_if($hazardCatalogItem !== null && (int) $hazardCatalogItem->organization_id !== $this->currentOrganizationId(), 404);

        return view('safety.hazard-catalog._dialog', ['item' => $hazardCatalogItem]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', HazardAssessment::class);
        HazardCatalogItem::query()->create($this->validated($request, true) + [
            'organization_id' => $this->currentOrganizationId(),
            'code' => 'own/' . Str::uuid()->toString(),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->toList('safety.hazard-catalog.index')->with('success', __('safety.catalog.flash.saved'));
    }

    public function update(Request $request, HazardCatalogItem $hazardCatalogItem): RedirectResponse {
        Gate::authorize('create', HazardAssessment::class);
        abort_unless((int) $hazardCatalogItem->organization_id === $this->currentOrganizationId(), 404);
        $hazardCatalogItem->update($this->validated($request, false));

        return redirect()->toList('safety.hazard-catalog.index')->with('success', __('safety.catalog.flash.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $create): array {
        $data = $request->validate([
            'category' => ['required', 'string', 'min:2', 'max:120'],
            'hazard' => ['required', 'string', 'min:2', 'max:255'],
            'measure' => ['nullable', 'string', 'max:10000'],
            'severity' => ['required', 'integer', 'between:1,5'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'category' => (string) $data['category'],
            'hazard' => (string) $data['hazard'],
            'measure' => $data['measure'] ?? null,
            'severity' => (int) $data['severity'],
            'likelihood' => (int) $data['likelihood'],
            'is_active' => $request->boolean('is_active', $create),
        ];
    }
}
