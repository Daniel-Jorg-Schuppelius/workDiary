<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountPasswordController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\UserSessionInvalidator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Validation\Rules\Password;

class AccountPasswordController extends Controller {
    public function edit(Request $request): View {
        $mustChange = (bool) (Auth::user()->must_change_password ?? false);

        // Wird die Route als Modal geladen (AJAX), nur das Dialog-Partial liefern;
        // bei direktem Aufruf die Vollseite im App-Layout rendern.
        if ($request->ajax()) {
            return view('account._password_dialog', ['mustChange' => $mustChange, 'isDialog' => true]);
        }

        return view('account.password', ['mustChange' => $mustChange]);
    }

    public function update(Request $request, UserSessionInvalidator $sessions): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $mustChange = (bool) ($user->must_change_password ?? false);

        $rules = [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];

        // Wenn der User sein Passwort regulär ändern will (nicht erzwungen),
        // verlangen wir das aktuelle Passwort.
        if (! $mustChange) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $request->validate($rules);

        // is_new_system aktivieren: sonst prüft der LegacyUserProvider beim Login
        // weiter das alte Klartext-Legacy-Passwort und ignoriert das neue bcrypt-PW.
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'is_new_system' => true,
            'must_change_password' => false,
        ])->save();

        // Passwort-Wechsel entwertet Sitzungen anderer Geräte (das eigene
        // bleibt aktiv); zusätzlich Session-ID rotieren (Fixation-Schutz am
        // Credential-Wechsel, ASVS V3).
        $sessions->invalidateOthers($user, $request->session()->getId());
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', __('Passwort wurde aktualisiert.'));
    }
}
