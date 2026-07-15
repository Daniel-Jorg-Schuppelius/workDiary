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

use App\Legacy\Support\LegacyConnectivity;
use App\Support\DatabaseHealth;
use Closure;
use Illuminate\Database\QueryException;
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
 * Zusätzlich wird ein Datei-Marker pro Connection gepflegt (DatabaseHealth):
 * - Vor dem Request wird geprüft, ob die Default-Connection als "down" markiert
 *   ist. Trifft das zu, geht sofort 503 raus — ohne erneut 3 s in den
 *   Connect-Timeout zu laufen.
 * - Bei einer Exception wird die betroffene Connection markiert.
 *
 * Diese Middleware muss in der web-Gruppe ganz oben (prepend) registriert sein,
 * damit StartSession innerhalb von $next ausgeführt wird und Exceptions hier
 * abgefangen werden, bevor sie an die globale Pipeline weitergegeben werden.
 */
class HandleDatabaseUnavailable {
    public function handle(Request $request, Closure $next): Response {
        $defaultConnection = DatabaseHealth::defaultConnection();

        // Fast-Path: Wenn die Default-Verbindung erst kürzlich versagt hat,
        // sparen wir uns die erneute Wartezeit.
        if (! DatabaseHealth::isAvailable($defaultConnection)) {
            return $this->renderUnavailable($request, null);
        }

        // Gleicher Fast-Path für den Legacy-Bereich: Er hängt vollständig an
        // der legacy-Connection. Ist die als down markiert, sofort 503 statt
        // pro Request erneut in den Connect-Timeout zu laufen.
        if ($request->is('legacy', 'legacy/*') && ! DatabaseHealth::isAvailable(LegacyConnectivity::CONNECTION)) {
            return $this->renderUnavailable($request, null);
        }

        try {
            return $next($request);
        } catch (Throwable $e) {
            if (! $this->isDatabaseUnavailable($e)) {
                throw $e;
            }

            $this->markFromException($e, $defaultConnection);

            return $this->renderUnavailable($request, $e);
        }
    }

    private function isDatabaseUnavailable(Throwable $e): bool {
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

    private function markFromException(Throwable $e, string $defaultConnection): void {
        // Bei QueryException kennt Laravel den exakten Verbindungsnamen,
        // sonst nehmen wir die Default-Connection an.
        $connection = $e instanceof QueryException && $e->connectionName !== ''
            ? $e->connectionName
            : $defaultConnection;

        DatabaseHealth::safeMarkUnavailable($connection);
    }

    private function renderUnavailable(Request $request, ?Throwable $e): Response {
        $message = $e?->getMessage() ?? 'Database temporarily unavailable.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Database temporarily unavailable.',
            ], 503);
        }

        return response()->view('errors.database-unavailable', [
            'exceptionMessage' => $message,
        ], 503);
    }
}
