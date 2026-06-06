<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocaleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller {
    public function switch(Request $request, string $locale): RedirectResponse {
        if (Locales::isSupported($locale)) {
            // Angemeldete Nutzer: dauerhaft in preferences.locale (gilt
            // geräteübergreifend). Gäste: nur Session.
            $user = Auth::user();
            if ($user instanceof User) {
                $prefs = (array) ($user->preferences ?? []);
                $prefs['locale'] = $locale;
                $user->preferences = $prefs;
                $user->save();
            }
            $request->session()->put('locale', $locale);
        }

        return redirect()->back();
    }
}
