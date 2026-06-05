<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SetLocale.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale {
    public function handle(Request $request, Closure $next): Response {
        $locale = (string) $request->session()->get('locale', config('app.locale', 'de'));
        if (! Locales::isSupported($locale)) {
            $locale = 'de';
        }
        App::setLocale($locale);
        Carbon::setLocale(Locales::carbon($locale));

        return $next($request);
    }
}
