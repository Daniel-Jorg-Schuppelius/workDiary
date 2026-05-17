<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HandleDatabaseUnavailable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Fängt Datenbank-Verbindungsfehler ab, die in nachgelagerten Middlewares
 * (z. B. StartSession bei SESSION_DRIVER=database) oder Controllern auftreten,
 * und liefert eine schlanke 503-Antwort aus, ohne dass die Session-/Cookie-
 * Schicht erneut auf die Datenbank zugreift.
 *
 * Diese Middleware muss in der web-Gruppe ganz oben (prepend) registriert sein,
 * damit StartSession innerhalb von $next ausgeführt wird und Exceptions hier
 * abgefangen werden, bevor sie an die globale Pipeline weitergegeben werden.
 */
class HandleDatabaseUnavailable
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            if (! $this->isDatabaseUnavailable($e)) {
                throw $e;
            }

            return $this->renderUnavailable($request, $e);
        }
    }

    private function isDatabaseUnavailable(Throwable $e): bool
    {
        $current = $e;
        while ($current !== null) {
            // QueryException erbt von PDOException, daher genügt diese Prüfung.
            if ($current instanceof PDOException) {
                return true;
            }
            $current = $current->getPrevious();
        }

        return false;
    }

    private function renderUnavailable(Request $request, Throwable $e): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Database temporarily unavailable.',
            ], 503);
        }

        return response()->view('errors.database-unavailable', [
            'exceptionMessage' => $e->getMessage(),
        ], 503);
    }
}
