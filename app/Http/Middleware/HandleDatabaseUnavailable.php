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

use App\Legacy\LegacyBridge;
use App\Support\DatabaseHealth;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Fängt DB-Verbindungsfehler nachgelagerter Middlewares/Controller ab und liefert 503,
 * ohne dass die Session-/Cookie-Schicht erneut auf die DB zugreift. Pflegt einen
 * Datei-Marker pro Connection (DatabaseHealth) für den Fast-Path.
 *
 * Muss in der web-Gruppe ganz oben (prepend) stehen, damit StartSession innerhalb von
 * $next läuft und Exceptions hier vor der globalen Pipeline abgefangen werden.
 */
class HandleDatabaseUnavailable {
    public function handle(Request $request, Closure $next): Response {
        $defaultConnection = DatabaseHealth::defaultConnection();

        // Fast-Path: kürzlich versagte Verbindung spart die erneute Wartezeit.
        if (! DatabaseHealth::isAvailable($defaultConnection)) {
            return $this->renderUnavailable($request, null);
        }

        // Gleicher Fast-Path für den Legacy-Bereich (hängt vollständig an der legacy-Connection).
        if ($request->is('legacy', 'legacy/*') && ! DatabaseHealth::isAvailable(LegacyBridge::CONNECTION)) {
            return $this->renderUnavailable($request, null);
        }

        try {
            return $next($request);
        } catch (Throwable $e) {
            if (! $this->isDatabaseUnavailable($e)) {
                throw $e;
            }

            $this->markFromException($e, $defaultConnection);

            // Ohne diese Zeile verschwindet die Ursache: der Nutzer sieht 503,
            // das Log schweigt, und im Betrieb ist nicht zu unterscheiden, ob
            // die Datenbank weg war oder eine einzelne Abfrage scheiterte.
            Log::error('database.unavailable', [
                'connection' => $e instanceof QueryException && $e->connectionName !== ''
                    ? $e->connectionName
                    : $defaultConnection,
                'path' => $request->path(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

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
        // QueryException kennt den exakten Verbindungsnamen, sonst Default-Connection.
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
