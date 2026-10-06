<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFilterPresetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveUserFilterPresetRequest;
use App\Models\Platform\UserFilterPreset;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;

class UserFilterPresetController extends Controller {
    public function index(Request $request): View {
        /** @var \App\Models\Platform\User $user */
        $user = Auth::user();

        $scope = $request->string('scope')->toString();
        // reorder(): die Beziehung sortiert schon nach sort_order, id — danach griff der Bereich nie.
        $query = $user->filterPresets()->getQuery()->reorder()
            ->when($scope !== '', fn($q) => $q->where('scope', $scope));
        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'scope' => 'scope',
            'name' => 'name',
            'is_default' => 'is_default',
        ], 'scope', 'asc');
        $presets = $query
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('filter_presets.index', [
            'presets' => $presets,
            'scope' => $scope,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function store(SaveUserFilterPresetRequest $request): RedirectResponse {
        /** @var \App\Models\Platform\User $user */
        $user = Auth::user();

        $data = $request->validated();
        $query = $data['query'] ?? [];
        $isDefault = (bool) ($data['is_default'] ?? false);

        DB::transaction(function () use ($user, $data, $query, $isDefault): void {
            if ($isDefault) {
                $user->filterPresets()
                    ->where('scope', $data['scope'])
                    ->update(['is_default' => false]);
            }

            $user->filterPresets()->create([
                'scope' => $data['scope'],
                'name' => $data['name'],
                'query' => $query,
                'is_default' => $isDefault,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        });

        return back()->with('status', __('Filter gespeichert.'));
    }

    public function edit(UserFilterPreset $preset): View {
        Gate::authorize('update', $preset);

        return view('filter_presets._form_dialog', ['preset' => $preset]);
    }

    public function update(SaveUserFilterPresetRequest $request, UserFilterPreset $preset): RedirectResponse {
        Gate::authorize('update', $preset);

        $data = $request->validated();
        $query = $data['query'] ?? $preset->query;
        $isDefault = (bool) ($data['is_default'] ?? false);

        /** @var \App\Models\Platform\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($user, $preset, $data, $query, $isDefault): void {
            if ($isDefault) {
                $user->filterPresets()
                    ->where('scope', $data['scope'])
                    ->where('id', '!=', $preset->id)
                    ->update(['is_default' => false]);
            }

            $preset->update([
                'scope' => $data['scope'],
                'name' => $data['name'],
                'query' => $query,
                'is_default' => $isDefault,
                'sort_order' => $data['sort_order'] ?? $preset->sort_order,
            ]);
        });

        return back()->with('status', __('Filter aktualisiert.'));
    }

    public function destroy(UserFilterPreset $preset): RedirectResponse {
        Gate::authorize('delete', $preset);
        $preset->delete();

        return back()->with('status', __('Filter gelöscht.'));
    }
}
