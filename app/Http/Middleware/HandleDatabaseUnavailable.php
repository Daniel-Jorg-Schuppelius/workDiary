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
            $this->logCachedUnavailable($request, $defaultConnection);

            return $this->renderUnavailable($request, null);
        }

        // Gleicher Fast-Path für den Legacy-Bereich (hängt vollständig an der legacy-Connection).
        if ($request->is('legacy', 'legacy/*') && ! DatabaseHealth::isAvailable(LegacyBridge::CONNECTION)) {
            $this->logCachedUnavailable($request, LegacyBridge::CONNECTION);

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

    /**
     * Dieselbe Regel wie im Exception-Handler ({@see DatabaseHealth::isConnectionFailure()}).
     *
     * Vorher genügte hier **irgendeine** PDOException in der Kette: ein
     * Sperrtimeout oder ein Feld ohne Vorgabewert hätte die Verbindung für
     * 60 s als ausgefallen markiert und jede Folge-Anfrage mit 503
     * beantwortet.
     */
    private function isDatabaseUnavailable(Throwable $e): bool {
        return DatabaseHealth::isConnectionFailure($e);
    }

    /**
     * Der Fast-Path schwieg bisher — und damit war ein 503 aus dem Marker
     * nicht aufzuklären: er entsteht später und an anderer Stelle als der
     * Fehler, der ihn gesetzt hat. Genau daran hingen die wandernden 503 in
     * der Testsuite. Alter und Grund des Markers stehen jetzt in der Meldung.
     */
    private function logCachedUnavailable(Request $request, string $connection): void {
        $info = DatabaseHealth::markerInfo($connection);

        Log::warning('database.unavailable.cached', [
            'connection' => $connection,
            'path' => $request->path(),
            'marker_age_seconds' => $info['age'],
            'marker_reason' => $info['reason'],
        ]);
    }

    private function markFromException(Throwable $e, string $defaultConnection): void {
        // QueryException kennt den exakten Verbindungsnamen, sonst Default-Connection.
        $connection = $e instanceof QueryException && $e->connectionName !== ''
            ? $e->connectionName
            : $defaultConnection;

        DatabaseHealth::safeMarkUnavailable($connection, DatabaseHealth::describe($e));
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
