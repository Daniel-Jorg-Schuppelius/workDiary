<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', config('app.locale', 'de'));
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'de';
        }
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
