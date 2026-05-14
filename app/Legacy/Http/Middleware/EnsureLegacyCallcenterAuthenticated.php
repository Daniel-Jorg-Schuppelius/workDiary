<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyCallcenterAuthenticated {
    public function handle(Request $request, Closure $next): Response {
        if (Auth::check() || $request->session()->has('legacy_callcenter_user')) {
            return $next($request);
        }

        return redirect()->route('legacy.callcenter.login');
    }
}
