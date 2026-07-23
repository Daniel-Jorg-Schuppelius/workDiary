<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetMergeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AssetMergeService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manuelles Zusammenführen doppelt angelegter Assets: Gegenüberstellung mit
 * Feld-für-Feld-Auswahl, dann Merge (Quelle wird gelöscht). Einstieg über den
 * „Zusammenführen"-Button der Asset-Detailseite.
 */
class AssetMergeController extends Controller {
    /**
     * Gegenüberstellung. Ohne gewähltes Ziel zeigt die Seite zunächst die
     * Zielgeräte-Auswahl (Quelle steht fest).
     */
    public function compare(Request $request): View {
        $source = $this->resolveAsset((string) $request->query('source'));
        Gate::authorize('delete', $source);

        $target = null;
        if ((string) $request->query('target') !== '') {
            $target = $this->resolveAsset((string) $request->query('target'));
            Gate::authorize('update', $target);
            abort_if($target->id === $source->id, 422, __('Quell- und Zielgerät sind identisch.'));
        }

        return view('assets.merge-compare', [
            'source' => $source,
            'target' => $target,
            'targets' => $target === null
                ? Asset::query()->whereKeyNot($source->id)->orderBy('name')->get(['id', 'name', 'asset_no'])
                : collect(),
        ]);
    }

    public function merge(Request $request, AssetMergeService $merger): RedirectResponse {
        $source = $this->resolveAsset((string) $request->input('source'));
        $target = $this->resolveAsset((string) $request->input('target'));

        Gate::authorize('delete', $source);
        Gate::authorize('update', $target);
        abort_if($target->id === $source->id, 422, __('Quell- und Zielgerät sind identisch.'));

        $overrides = [];
        foreach ((array) $request->input('prefer_source', []) as $field) {
            $overrides[(string) $field] = $source->getAttribute((string) $field);
        }

        $merger->merge($source, $target, $overrides);

        return redirect()
            ->route('assets.show', $target)
            ->with('status', __('Gerät „:source“ wurde in „:target“ zusammengeführt.', [
                'source' => $source->name ?: $source->asset_no,
                'target' => $target->name ?: $target->asset_no,
            ]));
    }

    private function resolveAsset(string $sqid): Asset {
        $asset = (new Asset)->resolveRouteBinding($sqid);
        abort_unless($asset instanceof Asset, 404);

        return $asset;
    }
}
