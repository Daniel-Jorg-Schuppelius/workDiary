<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account._profile_dialog', [
            'user' => Auth::user(),
            'isDialog' => true,
        ]);
    }

    /**
     * Vollansicht der Profileinstellungen mit Avatar-Upload und
     * persönlichen Präferenzen. Der Modal-Endpoint (edit) bleibt für
     * den Schnellzugriff aus dem Header erhalten.
     */
    public function settings(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('attachments');

        return view('account.settings', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->fill($data)->save();

        return back()->with('success', __('Profil aktualisiert.'));
    }

    /**
     * Speichert persönliche Präferenzen (Theme, Locale, Format,
     * Startseite). Werte aus der Whitelist in `config/personalization.php`
     * werden validiert; alles andere wird verworfen.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $themes = (array) config('personalization.themes', []);
        $startpages = (array) config('personalization.startpages', []);

        $data = $request->validate([
            'preferences.theme' => ['nullable', 'string', Rule::in($themes)],
            'preferences.locale' => ['nullable', 'string', 'max:10'],
            'preferences.date_format' => ['nullable', 'string', 'max:32'],
            'preferences.time_format' => ['nullable', 'string', 'max:32'],
            'preferences.startpage' => ['nullable', 'string', Rule::in($startpages)],
        ]);

        $clean = array_filter(
            (array) ($data['preferences'] ?? []),
            static fn ($v) => $v !== null && $v !== ''
        );

        $user->preferences = $clean === [] ? null : $clean;
        $user->save();

        return back()->with('success', __('Präferenzen gespeichert.'));
    }
}
