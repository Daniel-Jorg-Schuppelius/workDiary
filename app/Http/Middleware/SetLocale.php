<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale {
    public const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next) {
        $locale = (string) $request->session()->get('locale', config('app.locale', 'de'));
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'de';
        }
        App::setLocale($locale);

        return $next($request);
    }
}
