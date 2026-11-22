<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyUserAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\RequiresLegacyAdmin;
use App\Legacy\Http\Requests\SaveLegacyUserRequest;
use App\Legacy\Models\{LegacyDiaryEntry, LegacyNotdienst, LegacyOnCall, LegacyUser};
use App\Legacy\Support\LegacyView;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class LegacyUserAdminController extends Controller {
    use RequiresLegacyAdmin;

    private const array SORTS = ['id' => 'id', 'uname' => 'uname'];

    public function index(Request $request): View {
        $this->ensureAdmin();
        $classic = LegacyView::isClassic($request);
        // Das Altportal ordnet nach ID, die neue Ansicht nach Namen.
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, $classic ? 'id' : 'uname', 'asc');

        return view($classic ? 'legacy.classic.users.index' : 'legacy.users.index', [
            'users' => LegacyUser::query()->where('id', '>', 3)->orderBy(self::SORTS[$sort], $dir)->get(['id', 'uname', 'email']),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        $this->ensureAdmin();

        return view(LegacyView::pick($request, 'legacy.users._form_dialog', 'legacy.classic.users.form'), [
            'legacyUser' => null,
            'isEdit' => false,
            'isDialog' => true,
        ]);
    }

    public function store(SaveLegacyUserRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyUser::query()->create($request->validated());

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter angelegt.');
    }

    public function edit(Request $request, LegacyUser $user): View {
        $this->ensureAdmin();

        $this->ensureMutableLegacyUser($user);

        // Löschen fragt im Altportal auf einer eigenen Seite nach, mit Grund, falls noch Daten hängen.
        if (LegacyView::isClassic($request) && $request->boolean('delete')) {
            return view('legacy.classic.users.delete', ['legacyUser' => $user, 'blockedBy' => $this->dependencyKind($user)]);
        }

        return view(LegacyView::pick($request, 'legacy.users._form_dialog', 'legacy.classic.users.form'), [
            'legacyUser' => $user,
            'isEdit' => true,
            'isDialog' => true,
        ]);
    }

    public function update(SaveLegacyUserRequest $request, LegacyUser $user): RedirectResponse {
        $this->ensureAdmin();

        $this->ensureMutableLegacyUser($user);

        $data = array_filter($request->validated(), static fn($v) => $v !== null);
        $user->update($data);

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter aktualisiert.');
    }

    public function destroy(LegacyUser $user): RedirectResponse {
        $this->ensureAdmin();

        $this->ensureMutableLegacyUser($user);

        if ($this->dependencyKind($user) !== null) {
            return back()->with('error', 'Mitarbeiter kann nicht geloescht werden: es sind noch Legacy-Daten vorhanden.');
        }

        $user->delete();

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter geloescht.');
    }

    private function ensureMutableLegacyUser(LegacyUser $user): void {
        abort_if((int) $user->id <= 3, 403);
    }

    /** Welche Altdaten das Löschen verhindern: orders, oncall, notdienst oder nichts. */
    private function dependencyKind(LegacyUser $user): ?string {
        return match (true) {
            LegacyDiaryEntry::query()->where('user', $user->id)->exists() => 'orders',
            LegacyOnCall::query()->where('user', $user->id)->exists() => 'oncall',
            LegacyNotdienst::query()->where('user', $user->id)->exists() => 'notdienst',
            default => null,
        };
    }
}
