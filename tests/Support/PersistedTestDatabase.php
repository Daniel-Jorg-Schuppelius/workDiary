<?php
/*
 * Created on   : Sun Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersistedTestDatabase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Überspringt das teure migrate:fresh (Schema-Dump-Load + TestingSeeder, auf
 * MariaDB ~40s pro Prozess), wenn die Test-DB aus einem früheren Lauf noch
 * unverändert ist: RefreshDatabase rollt jede Test-Transaktion zurück, nach
 * einem Lauf ist die DB also exakt im frisch-geseedeten Zustand. Ob der noch
 * gilt, entscheidet ein Fingerprint über Schema-Dump, Migrationen, Seeder,
 * Factories, .env.testing und phpunit.xml. Der Marker liegt in der DB selbst
 * (Tabelle testing_schema_state) und fällt mit migrate:fresh automatisch weg.
 *
 * Escape-Hatches: TEST_DB_FRESH=1 oder --recreate-databases erzwingen einen
 * frischen Aufbau; jeder Fehler hier degradiert nur zum normalen migrate:fresh.
 */
final class PersistedTestDatabase {
    public const MARKER_TABLE = 'testing_schema_state';

    private static ?string $fingerprint = null;

    public static function enabled(): bool {
        if (!in_array(self::envValue('DB_CONNECTION'), ['mysql', 'mariadb'], true)) {
            return false;
        }
        if (self::envValue('TEST_DB_FRESH') !== null) {
            return false;
        }
        // --recreate-databases droppt die Worker-DB erst NACH unserem
        // Pre-Check (beim ersten App-Boot) — Skip wäre dann fatal.
        if (!empty($_SERVER['LARAVEL_PARALLEL_TESTING_RECREATE_DATABASES'])) {
            return false;
        }

        return true;
    }

    /** DB aus früherem Lauf vorhanden und Fingerprint unverändert? */
    public static function isPristine(): bool {
        try {
            $stmt = self::pdo()->query('SELECT `fingerprint` FROM `' . self::MARKER_TABLE . '` LIMIT 1');
            $value = $stmt === false ? null : $stmt->fetchColumn();

            return is_string($value) && hash_equals(self::fingerprint(), $value);
        } catch (Throwable) {
            return false; // DB oder Marker fehlt → regulär migrieren
        }
    }

    /**
     * Nach einem frischen migrate:fresh+seed aufrufen. Läuft bewusst über eine
     * eigene PDO-Verbindung: die Test-Transaktion des Haupt-Connections ist zu
     * diesem Zeitpunkt schon offen, der Marker muss aber committet werden.
     */
    public static function storeMarker(): void {
        try {
            $pdo = self::pdo();
            $pdo->exec('CREATE TABLE IF NOT EXISTS `' . self::MARKER_TABLE . '` (`fingerprint` VARCHAR(64) NOT NULL) ENGINE=InnoDB');
            $pdo->exec('DELETE FROM `' . self::MARKER_TABLE . '`');
            $stmt = $pdo->prepare('INSERT INTO `' . self::MARKER_TABLE . '` (`fingerprint`) VALUES (?)');
            $stmt->execute([self::fingerprint()]);
        } catch (Throwable) {
            // Marker ist reine Beschleunigung — nie fatal.
        }
    }

    /** DB gilt als verschmutzt (z. B. implizites DDL-Commit im Test). */
    public static function invalidate(): void {
        try {
            self::pdo()->exec('DROP TABLE IF EXISTS `' . self::MARKER_TABLE . '`');
        } catch (Throwable) {
        }
    }

    private static function pdo(): PDO {
        $database = self::databaseName();
        $socket = self::envValue('DB_SOCKET');
        $dsn = $socket !== null
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $database)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', self::envValue('DB_HOST', '127.0.0.1'), self::envValue('DB_PORT', '3306'), $database);

        return new PDO($dsn, (string) self::envValue('DB_USERNAME'), (string) self::envValue('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
    }

    /** Muss der Namenslogik von Illuminate TestDatabases::testDatabase() entsprechen. */
    public static function databaseName(): string {
        $database = (string) self::envValue('DB_DATABASE');
        $token = self::envValue('TEST_TOKEN');

        return $token !== null ? "{$database}_test_{$token}" : $database;
    }

    /**
     * Bewusst rohe Prozess-Umgebung statt env(): läuft vor dem App-Boot und
     * liest die von phpunit.xml/ParaTest gesetzten realen Env-Variablen.
     */
    private static function envValue(string $key, ?string $default = null): ?string {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Inhalts-Hash über alles, was den frisch-geseedeten DB-Zustand bestimmt.
     * Bewusst Datei-Inhalte statt env()-Werte: läuft vor dem App-Boot, wenn
     * .env.testing noch nicht geladen ist.
     */
    public static function fingerprint(): string {
        if (self::$fingerprint !== null) {
            return self::$fingerprint;
        }

        $root = dirname(__DIR__, 2);
        $parts = [];

        foreach (['.env.testing', 'phpunit.xml'] as $file) {
            $parts[] = $file . ':' . (is_file("{$root}/{$file}") ? md5_file("{$root}/{$file}") : '-');
        }

        foreach (['schema', 'migrations', 'seeders', 'factories'] as $dir) {
            self::hashDirectory("{$root}/database/{$dir}", $parts);
        }

        return self::$fingerprint = hash('sha256', implode("\n", $parts));
    }

    /** @param list<string> $parts */
    private static function hashDirectory(string $path, array &$parts): void {
        if (!is_dir($path)) {
            return;
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), ['php', 'sql'], true)) {
                $files[$file->getPathname()] = md5_file($file->getPathname());
            }
        }
        ksort($files);
        foreach ($files as $pathname => $hash) {
            $parts[] = $pathname . ':' . $hash;
        }
    }
}
