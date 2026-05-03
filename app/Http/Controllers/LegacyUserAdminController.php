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
    public function index(): View {
        $this->ensureAdmin();

        return view('legacy.users.index', [
            'users' => LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname', 'email']),
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

        LegacyUser::query()->create($data);

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter angelegt.');
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

        $user->update($data);

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter aktualisiert.');
    }

    public function destroy(LegacyUser $user): RedirectResponse {
        $this->ensureAdmin();

        abort_if((int) $user->id <= 3, 403);

        $hasDiary = LegacyDiaryEntry::query()->where('user', $user->id)->exists();
        $hasOnCall = LegacyOnCall::query()->where('user', $user->id)->exists();
        $hasNotdienst = LegacyNotdienst::query()->where('user', $user->id)->exists();

        if ($hasDiary || $hasOnCall || $hasNotdienst) {
            return back()->with('success', 'Mitarbeiter kann nicht geloescht werden: es sind noch Legacy-Daten vorhanden.');
        }

        $user->delete();

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter geloescht.');
    }

    private function ensureAdmin(): void {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }
}
