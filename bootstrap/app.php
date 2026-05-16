<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : app.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetOrganizationContext;
use App\Legacy\Http\Middleware\EnsureLegacyCallcenterAuthenticated;
use App\Legacy\Http\Middleware\EnsureLegacyWriteAllowed;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        // Datenbank nicht erreichbar (Connection refused / timeout / Auth-Fehler):
        // Zeige eine schlanke, layout-freie Fehlerseite, statt einen
        // generischen Whoops/500-Stack auszuwerfen. Wichtig: die Antwort
        // darf NICHT auf Session/DB zugreifen (kein layouts.app).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($e instanceof PDOException
                || $e instanceof QueryException
                || ($e->getPrevious() instanceof PDOException))) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database temporarily unavailable.',
                ], 503);
            }

            return response()->view('errors.database-unavailable', [
                'exceptionMessage' => $e->getMessage(),
            ], 503);
        });
    })->create();
