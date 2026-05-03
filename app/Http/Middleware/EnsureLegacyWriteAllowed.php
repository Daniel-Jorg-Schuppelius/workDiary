<?php

namespace App\Http\Middleware;

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
