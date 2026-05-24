<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TagController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Tag::class);

        $query = Tag::query()->withCount(['diaryEntries', 'shifts', 'assignments']);

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'color' => 'color',
            'diary' => 'diary_entries_count',
            'shifts' => 'shifts_count',
            'assignments' => 'assignments_count',
        ], 'name', 'asc');

        $tags = $query->paginate((int) setting('pagination.tags', 50))->withQueryString();

        return view('tags.index', compact('tags', 'sort', 'dir'));
    }

    public function create(Request $request): View {
        Gate::authorize('create', Tag::class);

        return view('tags._form_dialog', ['tag' => null, 'isDialog' => true]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Tag::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:' . (int) setting('validation.tag.name_max', 60), Rule::unique('tags', 'name')],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        Tag::create([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('tags.index')->with('success', __('Tag angelegt.'));
    }

    public function edit(Request $request, Tag $tag): View {
        Gate::authorize('update', $tag);

        return view('tags._form_dialog', ['tag' => $tag, 'isDialog' => true]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse {
        Gate::authorize('update', $tag);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:' . (int) setting('validation.tag.name_max', 60), Rule::unique('tags', 'name')->ignore($tag->id)],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        $tag->update($data);

        return redirect()->route('tags.index')->with('success', __('Tag aktualisiert.'));
    }

    public function destroy(Tag $tag): RedirectResponse {
        Gate::authorize('delete', $tag);

        $tag->delete();

        return redirect()->route('tags.index')->with('success', __('Tag gelöscht.'));
    }
}
