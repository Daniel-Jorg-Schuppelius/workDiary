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
use App\Support\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityCategoryController extends Controller {
    private const ALLOWED_SORTS = ['sort_order', 'key', 'label', 'activity_type', 'counts_as_work', 'billable_default', 'active'];

    public function index(\Illuminate\Http\Request $request): View {
        Gate::authorize('viewAny', ActivityCategory::class);

        $search = $request->string('q')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'sort_order';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $categories = ActivityCategory::query()
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->where('key', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $dir)
            ->paginate((int) Setting::get('pagination.activity_categories', 50))
            ->withQueryString();

        return view('activity-categories.index', compact('categories', 'search', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', ActivityCategory::class);

        return view('activity-categories._form_dialog', [
            'category' => null,
            'isEdit'   => false,
        ]);
    }

    public function edit(ActivityCategory $activityCategory): View {
        Gate::authorize('update', $activityCategory);

        return view('activity-categories._form_dialog', [
            'category' => $activityCategory,
            'isEdit'   => true,
        ]);
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
