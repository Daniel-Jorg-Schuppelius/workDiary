<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TagController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', Tag::class);

        $tags = Tag::query()
            ->withCount(['diaryEntries', 'shifts', 'assignments'])
            ->orderBy('name')
            ->paginate(50);

        return view('tags.index', compact('tags'));
    }

    public function create(Request $request): View {
        Gate::authorize('create', Tag::class);

        return view('tags._form_dialog', ['tag' => null, 'isDialog' => true]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Tag::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')],
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
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')->ignore($tag->id)],
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
