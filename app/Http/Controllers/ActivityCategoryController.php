<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveActivityCategoryRequest;
use App\Models\ActivityCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityCategoryController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', ActivityCategory::class);

        $categories = ActivityCategory::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('activity-categories.index', compact('categories'));
    }

    public function store(SaveActivityCategoryRequest $request): RedirectResponse {
        Gate::authorize('create', ActivityCategory::class);

        ActivityCategory::create($request->validated());

        return redirect()->route('activity-categories.index')
            ->with('success', __('Tätigkeit angelegt.'));
    }

    public function update(SaveActivityCategoryRequest $request, ActivityCategory $activityCategory): RedirectResponse {
        Gate::authorize('update', $activityCategory);

        $activityCategory->update($request->validated());

        return redirect()->route('activity-categories.index')
            ->with('success', __('Tätigkeit aktualisiert.'));
    }

    public function destroy(ActivityCategory $activityCategory): RedirectResponse {
        Gate::authorize('delete', $activityCategory);

        $activityCategory->delete();

        return redirect()->route('activity-categories.index')
            ->with('success', __('Tätigkeit gelöscht.'));
    }
}
