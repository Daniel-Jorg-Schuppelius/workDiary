<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureLegacyCallcenterAuthenticated.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyCallcenterAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || $request->session()->has('legacy_callcenter_user')) {
            return $next($request);
        }

        return redirect()->route('legacy.callcenter.login');
    }
}
