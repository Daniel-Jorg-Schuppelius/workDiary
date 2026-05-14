<?php

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetOrganizationContext;
use App\Legacy\Http\Middleware\EnsureLegacyCallcenterAuthenticated;
use App\Legacy\Http\Middleware\EnsureLegacyWriteAllowed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(__DIR__ . '/../routes/legacy.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            SetOrganizationContext::class,
            ForcePasswordChange::class,
        ]);
        $middleware->alias([
            'legacy.callcenter.auth' => EnsureLegacyCallcenterAuthenticated::class,
            'legacy.write' => EnsureLegacyWriteAllowed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
