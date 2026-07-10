<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserBookmarkController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserBookmarkRequest;
use App\Models\UserBookmark;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

class UserBookmarkController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', UserBookmark::class);
        $search = $request->string('q')->toString();
        $bookmarks = Auth::user()?->bookmarks()
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->whereLikeEscaped('label', $search)
                    ->orWhereLikeEscaped('url', $search);
            }))
            ->get() ?? collect();

        return view('bookmarks.index', compact('bookmarks', 'search'));
    }

    public function create(Request $request): View {
        Gate::authorize('create', UserBookmark::class);
        $bookmark = new UserBookmark([
            'url' => (string) $request->query('url', ''),
            'label' => (string) $request->query('label', ''),
        ]);

        return view('bookmarks._form_dialog', ['bookmark' => $bookmark, 'isEdit' => false]);
    }

    public function store(SaveUserBookmarkRequest $request): RedirectResponse {
        Gate::authorize('create', UserBookmark::class);
        $userId = (int) Auth::id();

        UserBookmark::create([
            'user_id' => $userId,
            'label' => $request->string('label')->toString(),
            'url' => $request->string('url')->toString(),
            'icon' => $request->input('icon') ?: null,
            'sort_order' => (int) ($request->input('sort_order') ?? 0),
        ]);

        return redirect()->route('bookmarks.index')->with('status', __('Lesezeichen gespeichert.'));
    }

    public function edit(UserBookmark $bookmark): View {
        Gate::authorize('update', $bookmark);

        return view('bookmarks._form_dialog', ['bookmark' => $bookmark, 'isEdit' => true]);
    }

    public function update(SaveUserBookmarkRequest $request, UserBookmark $bookmark): RedirectResponse {
        Gate::authorize('update', $bookmark);

        $bookmark->update([
            'label' => $request->string('label')->toString(),
            'url' => $request->string('url')->toString(),
            'icon' => $request->input('icon') ?: null,
            'sort_order' => (int) ($request->input('sort_order') ?? $bookmark->sort_order),
        ]);

        return redirect()->route('bookmarks.index')->with('status', __('Lesezeichen aktualisiert.'));
    }

    public function destroy(UserBookmark $bookmark): RedirectResponse {
        Gate::authorize('delete', $bookmark);
        $bookmark->delete();

        return redirect()->route('bookmarks.index')->with('status', __('Lesezeichen entfernt.'));
    }
}
