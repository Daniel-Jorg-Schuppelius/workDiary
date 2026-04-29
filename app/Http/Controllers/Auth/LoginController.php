<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginController extends Controller {
    public function showLoginForm(): View {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $this->syncLegacyUserIdIfMissing((string) $credentials['username']);

            $mode = session('work_mode', 'legacy');
            $legacyConfigured = filled(config('database.connections.legacy.database'));
            $defaultRoute = ($mode === 'legacy' && $legacyConfigured)
                ? route('legacy.diary.index')
                : route('diary.index');

            return redirect()->intended($defaultRoute);
        }

        return back()->withErrors([
            'username' => 'Benutzername oder Passwort ist falsch.',
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function syncLegacyUserIdIfMissing(string $submittedUsername): void {
        $authUser = Auth::user();

        if (! $authUser instanceof User || (int) ($authUser->legacy_user_id ?? 0) > 0) {
            return;
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return;
        }

        try {
            $legacy = DB::connection('legacy')
                ->table('user')
                ->select(['id', 'uname'])
                ->where('uname', $submittedUsername)
                ->first();

            if (! $legacy && filled($authUser->name)) {
                $legacy = DB::connection('legacy')
                    ->table('user')
                    ->select(['id', 'uname'])
                    ->where('uname', (string) $authUser->name)
                    ->first();
            }

            if ($legacy && (int) $legacy->id > 0) {
                $authUser->legacy_user_id = (int) $legacy->id;
                $authUser->save();
            }
        } catch (\Throwable) {
            // Legacy-Mapping ist ein Best-Effort und darf den Login nicht blockieren.
        }
    }
}
