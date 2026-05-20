<?php
/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatabaseHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use PDOException;
use Throwable;

/**
 * Merkt sich pro Datenbank-Verbindung, wann sie zuletzt nicht erreichbar war,
 * damit Folge-Requests nicht jeweils in den Connect-Timeout (meist 3 s) laufen.
 *
 * Der Marker wird bewusst als Datei im storage-Verzeichnis abgelegt — der
 * Laravel-Cache kann selbst auf die ausgefallene DB angewiesen sein
 * (CACHE_STORE=database). Eine Datei ist immer unabhängig erreichbar.
 */
final class DatabaseHealth {
    /** @var array<string, bool> Per-Request-Cache pro Connection. */
    private static array $requestUnavailable = [];

    public static function isAvailable(string $connection): bool {
        if (self::$requestUnavailable[$connection] ?? false) {
            return false;
        }

        $path = self::markerPath($connection);
        if (! is_file($path)) {
            return true;
        }

        $ttl = (int) config('database.unavailable_cache_ttl', 60);
        $mtime = @filemtime($path);

        if ($ttl > 0 && is_int($mtime) && $mtime + $ttl > time()) {
            self::$requestUnavailable[$connection] = true;

            return false;
        }

        // TTL abgelaufen: Marker entfernen, frische Prüfung zulassen.
        @unlink($path);

        return true;
    }

    public static function markUnavailable(string $connection): void {
        self::$requestUnavailable[$connection] = true;

        $path = self::markerPath($connection);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($path, (string) time());
    }

    public static function reset(?string $connection = null): void {
        if ($connection === null) {
            self::$requestUnavailable = [];
            // Hinweis: markerPath('*') w\u00fcrde das Wildcard via preg_replace zu '_'
            // sanitisieren. Daher das Glob-Pattern hier direkt bauen.
            $pattern = storage_path('framework/cache/db-down-*.flag');
            foreach ((array) glob($pattern) as $file) {
                @unlink((string) $file);
            }

            return;
        }

        unset(self::$requestUnavailable[$connection]);
        @unlink(self::markerPath($connection));
    }

    /**
     * Führt $work für die gegebene Connection aus und markiert diese bei
     * einer PDOException als unavailable. Gibt $default zurück, wenn die
     * Connection bereits als down markiert ist oder die Operation fehlschlägt.
     *
     * @template T
     *
     * @param  callable():T  $work
     * @param  T  $default
     * @return T
     */
    public static function attempt(string $connection, callable $work, mixed $default): mixed {
        if (! self::isAvailable($connection)) {
            return $default;
        }

        try {
            return $work();
        } catch (PDOException) {
            self::markUnavailable($connection);

            return $default;
        }
    }

    private static function markerPath(string $connection): string {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $connection) ?? 'default';

        return storage_path('framework/cache/db-down-' . $safe . '.flag');
    }

    public static function defaultConnection(): string {
        return (string) config('database.default', 'mysql');
    }

    /**
     * Best-effort-Aufräumen, das sich auch im fail-safe-Pfad benutzen lässt
     * — vermeidet Exceptions im Render-Handler.
     */
    public static function safeMarkUnavailable(string $connection): void {
        try {
            self::markUnavailable($connection);
        } catch (Throwable) {
            // ignore
        }
    }
}
