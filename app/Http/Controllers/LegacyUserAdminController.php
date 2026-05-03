<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Http\Requests\SaveLegacyUserRequest;
use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyOnCall;
use App\Models\Legacy\LegacyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LegacyUserAdminController extends Controller {
    use RequiresLegacyAdmin;
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

    public function store(SaveLegacyUserRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyUser::query()->create($request->validated());

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter angelegt.');
    }

    public function edit(LegacyUser $user): View {
        $this->ensureAdmin();

        $this->ensureMutableLegacyUser($user);

        return view('legacy.users.form', [
            'legacyUser' => $user,
            'isEdit' => true,
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

        if ($this->hasLegacyDependencies($user)) {
            return back()->with('error', 'Mitarbeiter kann nicht geloescht werden: es sind noch Legacy-Daten vorhanden.');
        }

        $user->delete();

        return redirect()->route('legacy.users.index')->with('success', 'Mitarbeiter geloescht.');
    }

    private function ensureMutableLegacyUser(LegacyUser $user): void {
        abort_if((int) $user->id <= 3, 403);
    }

    private function hasLegacyDependencies(LegacyUser $user): bool {
        return LegacyDiaryEntry::query()->where('user', $user->id)->exists()
            || LegacyOnCall::query()->where('user', $user->id)->exists()
            || LegacyNotdienst::query()->where('user', $user->id)->exists();
    }
}
