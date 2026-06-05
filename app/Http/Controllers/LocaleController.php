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

use App\Support\Locales;
use Illuminate\Http\{RedirectResponse, Request};

class LocaleController extends Controller {
    public function switch(Request $request, string $locale): RedirectResponse {
        if (Locales::isSupported($locale)) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->back();
    }
}
