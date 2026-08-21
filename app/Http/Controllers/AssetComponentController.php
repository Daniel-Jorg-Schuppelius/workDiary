<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComponentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Article, Asset, AssetComponent};
use App\Services\Asset\AssetComponentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Anlagen-Stückliste (Feature 118, MVP-607).
 */
class AssetComponentController extends Controller {
    public function __construct(private readonly AssetComponentService $components) {}

    public function index(Asset $asset): View {
        Gate::authorize('view', $asset);

        return view('assets.components.index', [
            'asset' => $asset,
            'installed' => $this->components->installed($asset),
            'history' => $this->components->history($asset),
            'due' => $this->components->dueComponents($asset),
        ]);
    }

    public function form(Asset $asset, ?AssetComponent $component = null): View {
        Gate::authorize('update', $asset);

        return view('assets.components._form_dialog', [
            'asset' => $asset,
            // Nicht 'component': innerhalb einer Blade-Komponente ist
            // $component reserviert (AnonymousComponent) und würde die
            // View-Variable überschreiben.
            'part' => $component,
            'articles' => Article::query()->orderBy('name')->limit(500)->get(['id', 'name', 'number', 'base_unit']),
        ]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);
        $data = $this->validated($request);

        AssetComponent::query()->create($data + [
            'organization_id' => $asset->organization_id,
            'asset_id' => $asset->id,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', __('asset.components.saved'));
    }

    /** Teil ersetzen: das alte bleibt mit Ausbaudatum in der Historie. */
    public function replace(Request $request, Asset $asset, AssetComponent $component): RedirectResponse {
        Gate::authorize('update', $asset);
        abort_unless((int) $component->asset_id === (int) $asset->id, 404);

        try {
            $this->components->replace($component, $this->validated($request), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('asset.components.replaced'));
    }

    public function remove(Asset $asset, AssetComponent $component): RedirectResponse {
        Gate::authorize('update', $asset);
        abort_unless((int) $component->asset_id === (int) $asset->id, 404);

        $component->forceFill([
            'status' => AssetComponent::STATUS_REMOVED,
            'removed_on' => now()->toDateString(),
        ])->save();

        return back()->with('status', __('asset.components.removed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge([
            'article_id' => $request->filled('article_id')
                ? Sqid::decodeOrNumeric(Article::class, (string) $request->input('article_id'))
                : null,
        ]);

        return $request->validate([
            'article_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('articles')],
            'label' => ['nullable', 'string', 'max:191', 'required_without:article_id'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'unit' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'installed_on' => ['nullable', 'date'],
            'replace_interval_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
