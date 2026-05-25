<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFilterPresetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserFilterPresetRequest;
use App\Models\UserFilterPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserFilterPresetController extends Controller {
    public function index(Request $request): View {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $scope = $request->string('scope')->toString();
        $presets = $user->filterPresets()
            ->when($scope !== '', fn($q) => $q->where('scope', $scope))
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('filter_presets.index', [
            'presets' => $presets,
            'scope' => $scope,
        ]);
    }

    public function store(SaveUserFilterPresetRequest $request): RedirectResponse {
        /** @var \App\Models\User $user */
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

    public function update(SaveUserFilterPresetRequest $request, UserFilterPreset $preset): RedirectResponse {
        Gate::authorize('update', $preset);

        $data = $request->validated();
        $query = $data['query'] ?? $preset->query;
        $isDefault = (bool) ($data['is_default'] ?? false);

        /** @var \App\Models\User $user */
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
