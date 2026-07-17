<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventCategoryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesIndexQuery;
use App\Models\EventCategory;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventCategoryController extends Controller {
    use ParsesIndexQuery;

    private const ALLOWED_SORTS = ['name', 'requires_certificate', 'certificate_valid_months', 'is_active'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', EventCategory::class);
        ['sort' => $sort, 'dir' => $dir] = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');
        $categories = EventCategory::query()->orderBy($sort, $dir)->paginate(50)->withQueryString();

        return view('event-categories.index', compact('categories', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', EventCategory::class);

        return view('event-categories._form_dialog', ['category' => null, 'isEdit' => false]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', EventCategory::class);
        EventCategory::create($this->validateCategory($request));

        return redirect()->route('event-categories.index')->with('success', __('Kategorie angelegt.'));
    }

    public function edit(EventCategory $category): View {
        Gate::authorize('update', $category);

        return view('event-categories._form_dialog', ['category' => $category, 'isEdit' => true]);
    }

    public function update(Request $request, EventCategory $category): RedirectResponse {
        Gate::authorize('update', $category);
        $category->update($this->validateCategory($request));

        return redirect()->route('event-categories.index')->with('success', __('Kategorie aktualisiert.'));
    }

    public function destroy(EventCategory $category): RedirectResponse {
        Gate::authorize('delete', $category);
        $category->delete();

        return redirect()->route('event-categories.index')->with('success', __('Kategorie gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateCategory(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'color' => ['nullable', 'string', 'max:9'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_certificate' => ['sometimes', 'boolean'],
            'certificate_valid_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'reminder_offsets' => ['nullable', 'array'],
            'reminder_offsets.*' => ['integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['requires_certificate'] ??= false;
        $data['is_active'] ??= false;

        return $data;
    }
}
