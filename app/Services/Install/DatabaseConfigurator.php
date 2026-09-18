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

use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Facades\{Cache, Config, DB};
use PDO;
use Spatie\Permission\PermissionRegistrar;
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
                // Ein Fehlschlag von touch() landet im catch unten (false).
                if ($database !== ':memory:' && ! File::isFile($database)) {
                    File::touch($database);
                }

                return $database === ':memory:' || File::isFile($database);
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
            if ($database !== ':memory:' && ! File::isFile($database)) {
                try {
                    File::touch($database);
                } catch (Throwable) {
                    // Best effort: die Migration meldet eine fehlende Datei selbst.
                }
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

        $this->rebindDatabaseCaches();
    }

    /**
     * Cache-Stores mit database-Treiber (und Failover-Stores darüber) halten die
     * beim ersten Zugriff aufgelöste Verbindung fest, ebenso der beim Boot
     * erzeugte Spatie-Registrar. Ohne Neuaufbau löschen Migrationen/Seeder den
     * Permission-Cache weiter in der Alt-DB („no such table: cache“).
     */
    private function rebindDatabaseCaches(): void {
        $stores = array_keys(array_filter(
            (array) Config::get('cache.stores', []),
            static fn(mixed $store): bool => is_array($store) && in_array($store['driver'] ?? null, ['database', 'failover'], true),
        ));
        Cache::forgetDriver($stores);

        if (app()->resolved(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->initializeCache();
        }
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
