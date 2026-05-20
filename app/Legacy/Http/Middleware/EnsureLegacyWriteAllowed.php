<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureLegacyWriteAllowed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyWriteAllowed {
    public function handle(Request $request, Closure $next): Response {
        // Lese-Anfragen passieren immer durch.
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if ((bool) config('app.legacy_write_enabled', false)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Legacy-System ist read-only. Bitte das neue System verwenden.'),
            ], 423);
        }

        return back()->with('error', __('Legacy-System ist read-only. Bitte das neue System verwenden.'));
    }
}
