<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserBookmarkController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveUserBookmarkRequest;
use App\Models\Platform\{User, UserBookmark};
use App\Support\SortableQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

class UserBookmarkController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', UserBookmark::class);
        $search = $request->string('q')->toString();
        /** @var User $user */
        $user = Auth::user();
        // reorder(): die Beziehung sortiert schon nach sort_order — die gewählte Spalte muss vorn stehen.
        $query = $user->bookmarks()->getQuery()->reorder()
            ->when($search !== '', fn($q) => $q->search($search));
        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'sort_order' => 'sort_order',
            'label' => 'label',
            'url' => 'url',
        ], 'sort_order', 'asc');
        $bookmarks = $query->orderBy('id')->paginate(25)->withQueryString();

        return view('bookmarks.index', compact('bookmarks', 'search', 'sort', 'dir'));
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

        return redirect()->toList('bookmarks.index')->with('status', __('Lesezeichen gespeichert.'));
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

        return redirect()->toList('bookmarks.index')->with('status', __('Lesezeichen aktualisiert.'));
    }

    public function destroy(UserBookmark $bookmark): RedirectResponse {
        Gate::authorize('delete', $bookmark);
        $bookmark->delete();

        return redirect()->toList('bookmarks.index')->with('status', __('Lesezeichen entfernt.'));
    }
}
