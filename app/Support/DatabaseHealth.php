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

use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
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

    /** @var array<string, string> Grund je Connection — für die Meldung, wenn kein Marker auf Platte liegt. */
    private static array $requestReason = [];

    /**
     * Client-/Server-Codes, die ein **Verbindungs**problem bezeichnen.
     *
     * MariaDB meldet fast alles unter SQLSTATE `HY000` — ein Sperrtimeout
     * (1205), ein fehlender Spaltenvorgabewert (1364) und „Out of resources
     * when opening file" tragen denselben Zustand wie ein echter
     * Verbindungsabbruch. Wer HY000 pauschal als „Datenbank weg" liest,
     * sperrt nach einem einzelnen Abfragefehler die ganze Anwendung für 60 s
     * aus — und genau so entstanden die wandernden 503 in der Testsuite.
     */
    private const CONNECTION_DRIVER_CODES = [
        1040, // Too many connections
        1042, // Unable to connect to any of the specified MySQL hosts
        1043, // Bad handshake
        1044, // Access denied for user to database
        1045, // Access denied for user
        1049, // Unknown database
        1053, // Server shutdown in progress
        1129, // Host blocked
        1130, // Host not allowed to connect
        2002, // Can't connect through socket
        2003, // Can't connect to server
        2006, // Server has gone away
        2013, // Lost connection during query
    ];

    /**
     * Ist die Ausnahme ein **Verbindungs**problem — oder nur eine
     * fehlgeschlagene Abfrage auf einer erreichbaren Datenbank?
     *
     * Einzige Stelle, an der diese Unterscheidung getroffen wird: sie
     * entscheidet, ob eine Verbindung als ausgefallen markiert und damit für
     * 60 s jede Folge-Anfrage mit 503 beantwortet wird.
     */
    public static function isConnectionFailure(Throwable $e): bool {
        $pdo = $e instanceof PDOException ? $e : $e->getPrevious();
        while ($pdo !== null && ! $pdo instanceof PDOException) {
            $pdo = $pdo->getPrevious();
        }

        if (! $pdo instanceof PDOException) {
            return false;
        }

        $info = is_array($pdo->errorInfo ?? null) ? $pdo->errorInfo : [];
        $sqlState = isset($info[0]) ? (string) $info[0] : '';
        $driverCode = isset($info[1]) ? (int) $info[1] : 0;

        // Ohne errorInfo lief noch keine Abfrage — typisch für einen
        // gescheiterten Verbindungsaufbau.
        return $sqlState === ''
            || str_starts_with($sqlState, '08')
            || in_array($sqlState, ['57P01', '57P02', '57P03'], true)
            || ($sqlState === 'HY000' && in_array($driverCode, self::CONNECTION_DRIVER_CODES, true));
    }

    /** Kurzbeschreibung einer Ausnahme für Marker und Protokoll. */
    public static function describe(Throwable $e): string {
        return $e::class . ': ' . mb_substr($e->getMessage(), 0, 200);
    }

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

    /**
     * @param  string|null  $reason  Woher der Marker stammt — landet im Marker
     *                               und später in der Fast-Path-Meldung.
     *
     * Ohne den Grund ist ein 503 aus dem Fast-Path nicht aufzuklären: er
     * entsteht *später* und an *anderer Stelle* als der Fehler, der ihn
     * ausgelöst hat. Genau das machte die wandernden 503 in der Testsuite so
     * zäh (Vollscan 2026-08-23).
     */
    public static function markUnavailable(string $connection, ?string $reason = null): void {
        self::$requestUnavailable[$connection] = true;
        if ($reason !== null) {
            self::$requestReason[$connection] = $reason;
        }

        $path = self::markerPath($connection);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            ToolkitFile::write($path, time() . ($reason !== null ? '|' . str_replace(["\n", "\r"], ' ', $reason) : ''));
        } catch (Throwable) {
            // Best-effort Marker: DB-Ausfallpfade dürfen nicht durch Dateifehler kippen.
        }
    }

    /**
     * Grund und Alter des gesetzten Markers — für die Meldung im Fast-Path.
     *
     * @return array{age: int|null, reason: string|null}
     */
    public static function markerInfo(string $connection): array {
        $path = self::markerPath($connection);

        if (! is_file($path)) {
            // Der Fast-Path kann auch aus dem Prozess-Speicher feuern (gleicher
            // Request, Marker noch nicht oder nicht mehr auf Platte) — dann
            // steht der Grund nur hier.
            return ['age' => null, 'reason' => self::$requestReason[$connection] ?? null];
        }

        $content = (string) @file_get_contents($path);
        [$stamp, $reason] = array_pad(explode('|', $content, 2), 2, null);

        return [
            'age' => is_numeric($stamp) ? time() - (int) $stamp : null,
            'reason' => $reason !== null && $reason !== '' ? $reason : null,
        ];
    }

    public static function reset(?string $connection = null): void {
        if ($connection === null) {
            self::$requestUnavailable = [];
            self::$requestReason = [];
            // Hinweis: markerPath('*') würde das Wildcard via preg_replace zu '_'
            // sanitisieren. Daher das Glob-Pattern hier direkt bauen — MIT dem
            // Worker-Suffix: ohne ihn räumte `TestCase::setUp()` eines Workers
            // die Marker aller anderen mit weg, mitten in deren Test.
            $pattern = storage_path('framework/cache/db-down-*' . self::workerSuffix() . '.flag');
            foreach ((array) glob($pattern) as $file) {
                @unlink((string) $file);
            }

            return;
        }

        unset(self::$requestUnavailable[$connection], self::$requestReason[$connection]);
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

        return storage_path('framework/cache/db-down-' . $safe . self::workerSuffix() . '.flag');
    }

    /**
     * Unter ParaTest je Worker isolieren (TEST_TOKEN): ein im Test gesetzter
     * Marker darf weder in parallele Test-Prozesse leaken noch von ihnen
     * gelöscht werden. Bewusst ohne env()/Facade, damit der Pfad auch im
     * Fail-Safe ohne Container bestimmbar bleibt.
     */
    private static function workerSuffix(): string {
        $token = (string) ($_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? '');

        return $token === '' ? '' : '-' . (preg_replace('/[^a-zA-Z0-9_-]/', '_', $token) ?? '');
    }

    public static function defaultConnection(): string {
        return (string) config('database.default', 'mysql');
    }

    /**
     * Best-effort-Aufräumen, das sich auch im fail-safe-Pfad benutzen lässt
     * — vermeidet Exceptions im Render-Handler.
     */
    public static function safeMarkUnavailable(string $connection, ?string $reason = null): void {
        try {
            self::markUnavailable($connection, $reason);
        } catch (Throwable) {
            // ignore
        }
    }
}
