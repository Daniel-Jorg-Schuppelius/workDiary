<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForcePasswordChange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Routen, die auch bei erzwungenem Passwortwechsel erreichbar sein müssen.
     */
    private const ALLOWED_ROUTES = [
        'account.password.edit',
        'account.password.update',
        'account.profile.edit',
        'account.profile.update',
        'logout',
        'locale.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || empty($user->must_change_password)) {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Bitte legen Sie zuerst ein neues Passwort fest.'),
                'must_change_password' => true,
            ], 423);
        }

        return redirect()->route('account.password.edit')
            ->with('warning', __('Bitte legen Sie zuerst ein neues Passwort fest.'));
    }
}
