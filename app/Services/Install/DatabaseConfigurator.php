<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatabaseConfigurator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Install;

use Illuminate\Support\Facades\{Config, DB};
use PDO;
use Throwable;

/**
 * Installer-Baustein Datenbank: Verbindungstest, .env-Persistenz und
 * Laufzeit-Aktivierung der konfigurierten Verbindung. Aus dem
 * InstallationManager extrahiert (Refactoring Welle 2, B6b); dieser bleibt
 * die Fassade.
 */
class DatabaseConfigurator {
    public function __construct(private readonly EnvWriter $env) {}

    /** @return list<string> benötigte PHP-Extensions je Treiber */
    public function driverExtensions(?string $driver): array {
        return match ($driver) {
            'mysql' => ['pdo_mysql'],
            'pgsql' => ['pdo_pgsql'],
            'sqlite' => ['pdo_sqlite'],
            default => [],
        };
    }

    /**
     * Testet eine Datenbank-Verbindung mit den übergebenen Parametern, ohne
     * die Laufzeit-Config dauerhaft zu verändern.
     *
     * @param  array<string, string|int|null>  $config
     */
    public function testConnection(array $config): bool {
        $driver = (string) ($config['driver'] ?? 'sqlite');

        try {
            if ($driver === 'sqlite') {
                $database = (string) ($config['database'] ?? database_path('database.sqlite'));
                if ($database !== ':memory:' && ! is_file($database)) {
                    @touch($database);
                }

                return is_file($database) || $database === ':memory:';
            }

            $pdo = new PDO(
                $this->dsn($config),
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $pdo->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Persistiert die Datenbank-Konfiguration in der .env und aktiviert sie
     * für die laufende Runtime (damit Migrationen direkt laufen können).
     *
     * @param  array<string, string|int|null>  $config
     */
    public function configureDatabase(array $config): void {
        $driver = (string) ($config['driver'] ?? 'sqlite');
        $this->env->ensureFileExists();

        if ($driver === 'sqlite') {
            $database = (string) ($config['database'] ?? database_path('database.sqlite'));
            if ($database !== ':memory:' && ! is_file($database)) {
                @touch($database);
            }

            $this->env->setMany([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $database,
            ]);
        } else {
            $this->env->setMany([
                'DB_CONNECTION' => $driver,
                'DB_HOST' => (string) ($config['host'] ?? '127.0.0.1'),
                'DB_PORT' => (string) ($config['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)),
                'DB_DATABASE' => (string) ($config['database'] ?? ''),
                'DB_USERNAME' => (string) ($config['username'] ?? ''),
                'DB_PASSWORD' => (string) ($config['password'] ?? ''),
            ]);
        }

        $this->applyDatabaseToRuntime($config);
    }

    /**
     * Liest die in der .env hinterlegte Datenbank-Konfiguration und aktiviert
     * sie für die laufende Runtime. Nötig in Wizard-Schritten nach dem
     * Datenbank-Schritt, die in eigenen Requests laufen und sonst eine
     * (gecachte) Alt-Verbindung verwenden würden.
     */
    public function applyConfiguredDatabaseToRuntime(): void {
        $driver = $this->env->get('DB_CONNECTION');

        // Nur eingreifen, wenn die .env eine Verbindung definiert. Ohne
        // Eintrag (z. B. in Tests) bleibt die bestehende Runtime-Verbindung
        // unangetastet.
        if (! is_string($driver) || $driver === '') {
            return;
        }

        $this->applyDatabaseToRuntime([
            'driver' => $driver,
            'host' => $this->env->get('DB_HOST'),
            'port' => $this->env->get('DB_PORT'),
            'database' => $this->env->get('DB_DATABASE'),
            'username' => $this->env->get('DB_USERNAME'),
            'password' => $this->env->get('DB_PASSWORD'),
        ]);
    }

    /**
     * @param  array<string, string|int|null>  $config
     */
    private function applyDatabaseToRuntime(array $config): void {
        $driver = (string) ($config['driver'] ?? 'sqlite');
        Config::set('database.default', $driver);

        if ($driver === 'sqlite') {
            Config::set('database.connections.sqlite.database', (string) ($config['database'] ?? database_path('database.sqlite')));
        } else {
            Config::set("database.connections.{$driver}.host", (string) ($config['host'] ?? '127.0.0.1'));
            Config::set("database.connections.{$driver}.port", (string) ($config['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)));
            Config::set("database.connections.{$driver}.database", (string) ($config['database'] ?? ''));
            Config::set("database.connections.{$driver}.username", (string) ($config['username'] ?? ''));
            Config::set("database.connections.{$driver}.password", (string) ($config['password'] ?? ''));
        }

        DB::purge($driver);
        DB::reconnect($driver);
    }

    /**
     * @param  array<string, string|int|null>  $config
     */
    private function dsn(array $config): string {
        $driver = (string) ($config['driver'] ?? 'mysql');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
        $database = (string) ($config['database'] ?? '');

        return $driver === 'pgsql'
            ? "pgsql:host={$host};port={$port};dbname={$database}"
            : "mysql:host={$host};port={$port};dbname={$database}";
    }
}
