<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HomeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller {
    public function __invoke(Request $request): View|RedirectResponse {
        $canViewSensitive = Auth::check();
        $legacyConfigured = filled(config('database.connections.legacy.database'));

        /** @var User|null $user */
        $user = Auth::user();

        // Ohne Session-Modus greift die persistierte Modus-Wahl statt hart 'legacy'
        // (sonst fällt F5/neuer Login in Legacy zurück).
        $default = $user instanceof User ? $user->preferredWorkMode() : 'legacy';
        $currentMode = $request->session()->get('work_mode', $default);

        if ($user instanceof User) {
            // Modus auf einen tatsächlich erlaubten Wert korrigieren.
            if ($currentMode === 'legacy' && ! $user->canAccessLegacy()) {
                $currentMode = 'new';
                $request->session()->put('work_mode', 'new');
            } elseif ($currentMode === 'new' && ! $user->canAccessNew()) {
                $currentMode = 'legacy';
                $request->session()->put('work_mode', 'legacy');
            }
        }

        if (
            $canViewSensitive && $currentMode === 'legacy' && $legacyConfigured
            && (! $user instanceof User || $user->canAccessLegacy())
        ) {
            return redirect()->route('legacy.diary.week');
        }

        if ($canViewSensitive && (! $user instanceof User || $user->canAccessNew())) {
            return redirect()->route('dashboard');
        }

        // Ab hier nur noch Gäste: öffentliche Produkt-Landingpage.
        return view('home');
    }

    public function switchMode(Request $request, string $mode): RedirectResponse {
        if (! in_array($mode, ['legacy', 'new'], true)) {
            return back()->with('success', __('Unbekannter Modus.'));
        }

        $legacyConfigured = filled(config('database.connections.legacy.database'));
        if ($mode === 'legacy' && ! $legacyConfigured) {
            $request->session()->put('work_mode', 'new');

            return back()->with('success', __('Legacy-Modus ist nicht verfügbar (Legacy-DB nicht konfiguriert).'));
        }

        /** @var User|null $user */
        $user = $request->user();
        if ($user instanceof User) {
            if ($mode === 'legacy' && ! $user->canAccessLegacy()) {
                return back()->with('error', __('Kein Zugriff auf das Legacy-System.'));
            }
            if ($mode === 'new' && ! $user->canAccessNew()) {
                return back()->with('error', __('Kein Zugriff auf das neue System.'));
            }
        }

        $request->session()->put('work_mode', $mode);

        // Wahl dauerhaft am User merken, damit sie Session-Ablauf/Login/F5 überlebt
        // (siehe EnsureNewSystemAccess / HomeController-Default).
        if ($user instanceof User && $user->getPreference('work_mode') !== $mode) {
            $user->setPreference('work_mode', $mode);
        }

        $origin = (string) $request->input('origin', 'home');
        $targetRoute = $this->resolveModeRoute($origin, $mode);

        return redirect()
            ->route($targetRoute)
            ->with('mode_toast', $mode === 'legacy' ? __('Legacy-Modus aktiviert.') : __('Neuer Modus aktiviert.'));
    }

    private function resolveModeRoute(string $origin, string $mode): string {
        if ($origin === 'home') {
            return 'home';
        }

        return match ($origin) {
            'legacy.diary.index', 'diary.index' => $mode === 'legacy' ? 'legacy.diary.index' : 'diary.index',
            'legacy.diary.create', 'diary.create' => $mode === 'legacy' ? 'legacy.diary.create' : 'diary.create',
            default => $mode === 'legacy' ? 'legacy.diary.index' : 'diary.index',
        };
    }
}
