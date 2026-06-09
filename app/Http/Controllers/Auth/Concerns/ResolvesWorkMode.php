<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesWorkMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Bestimmt nach erfolgreichem Login den Arbeitsmodus (legacy/new) anhand der
 * tatsächlichen Zugriffsrechte und leitet zur passenden Startseite. Wird von
 * LoginController (ohne 2FA) und TwoFactorChallengeController (nach 2FA) genutzt.
 */
trait ResolvesWorkMode {
    protected function applyWorkModeAndRedirect(Request $request, User $user): RedirectResponse {
        $legacyConfigured = filled(config('database.connections.legacy.database'));
        // Beim Login die zuletzt persistierte Modus-Wahl des Users übernehmen,
        // statt hart auf 'legacy' zu defaulten.
        $sessionMode = (string) session('work_mode', $user->preferredWorkMode());

        $canLegacy = $legacyConfigured && $user->canAccessLegacy();
        $canNew = $user->canAccessNew();

        if ($sessionMode === 'legacy' && ! $canLegacy && $canNew) {
            $sessionMode = 'new';
        } elseif ($sessionMode === 'new' && ! $canNew && $canLegacy) {
            $sessionMode = 'legacy';
        } elseif (! $canLegacy && ! $canNew) {
            $sessionMode = 'legacy';
        }
        $request->session()->put('work_mode', $sessionMode);

        $defaultRoute = ($sessionMode === 'legacy' && $canLegacy)
            ? route('legacy.diary.index')
            : ($canNew ? route('diary.index') : route('home'));

        return redirect()->intended($defaultRoute);
    }
}
