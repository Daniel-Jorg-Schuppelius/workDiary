<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Models\Legacy\LegacyUser;
use App\Support\LegacyRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyUserAdminController extends Controller {
    public function index(Request $request): View {
        $this->ensureAdmin();

        $sortableColumns = [
            'id' => 'id',
            'uname' => 'uname',
            'email' => 'email',
        ];

        $sort = (string) $request->query('sort', 'uname');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortColumn = $sortableColumns[$sort] ?? $sortableColumns['uname'];

        $users = LegacyUser::query()
            ->where('id', '>', 3)
            ->orderBy($sortColumn, $dir)
            ->get(['id', 'uname', 'email']);

        return view('legacy.users.index', [
            'users' => $users,
            'sort' => array_key_exists($sort, $sortableColumns) ? $sort : 'uname',
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        $this->ensureAdmin();

        return view('legacy.users.form', [
            'legacyUser' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $this->ensureAdmin();

        $data = $request->validate([
            'uname' => ['required', 'string', 'max:100', 'unique:legacy.user,uname'],
            'userpw' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $data['email'] = $data['email'] ?? '';

        LegacyUser::query()->create($data);

        return redirect()->route('legacy.users.index')->with('success', __('Mitarbeiter angelegt.'));
    }

    public function edit(LegacyUser $user): View {
        $this->ensureAdmin();

        abort_if((int) $user->id <= 3, 403);

        return view('legacy.users.form', [
            'legacyUser' => $user,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, LegacyUser $user): RedirectResponse {
        $this->ensureAdmin();

        abort_if((int) $user->id <= 3, 403);

        $data = $request->validate([
            'uname' => ['required', 'string', 'max:100', 'unique:legacy.user,uname,' . $user->id],
            'userpw' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $data['email'] = $data['email'] ?? '';

        $user->update($data);

        return redirect()->route('legacy.users.index')->with('success', __('Mitarbeiter aktualisiert.'));
    }

    public function destroy(LegacyUser $user): RedirectResponse {
        $this->ensureAdmin();

        abort_if((int) $user->id <= 3, 403);

        $hasDiary = LegacyDiaryEntry::query()->where('user', $user->id)->exists();
        $hasOnCall = LegacyOnCall::query()->where('user', $user->id)->exists();
        $hasNotdienst = LegacyNotdienst::query()->where('user', $user->id)->exists();

        if ($hasDiary || $hasOnCall || $hasNotdienst) {
            return back()->with('success', __('Mitarbeiter kann nicht gelöscht werden: es sind noch Legacy-Daten vorhanden.'));
        }

        $user->delete();

        return redirect()->route('legacy.users.index')->with('success', __('Mitarbeiter gelöscht.'));
    }

    private function ensureAdmin(): void {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }
}
