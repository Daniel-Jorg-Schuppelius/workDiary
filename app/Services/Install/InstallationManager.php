<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallationManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Install;

use App\Enums\User\UserRole;
use App\Models\{Organization, User};
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\{Artisan, Config, DB, Hash};
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Kapselt die gesamte Installationslogik für den Web-Installer und das
 * begleitende `app:install` Artisan-Command.
 *
 * Die Installation gilt als abgeschlossen, sobald die Lock-Datei
 * {@see self::lockPath()} existiert. Solange sie fehlt, leitet
 * {@see \App\Http\Middleware\RedirectIfNotInstalled} auf den Wizard um.
 */
class InstallationManager {
    /** Unterstützte Datenbank-Treiber im Installer. */
    public const DRIVERS = ['sqlite', 'mysql', 'pgsql'];

    private readonly string $lockPath;

    public function __construct(private readonly EnvWriter $env, ?string $lockPath = null) {
        $this->lockPath = $lockPath ?? storage_path('installed');
    }

    public static function make(): self {
        return new self(EnvWriter::forApp());
    }

    public function env(): EnvWriter {
        return $this->env;
    }

    // ── Installationsstatus ──────────────────────────────────────────────

    public function isInstalled(): bool {
        $flag = config('app.installed');
        if ($flag !== null) {
            return (bool) $flag;
        }

        return is_file($this->lockPath);
    }

    public function markInstalled(): void {
        $dir = dirname($this->lockPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => (string) config('app.version', '1.0.0'),
        ], JSON_PRETTY_PRINT) ?: '{}';

        if (@file_put_contents($this->lockPath, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Konnte Installations-Marker nicht schreiben: ' . $this->lockPath);
        }
    }

    public function lockPath(): string {
        return $this->lockPath;
    }

    // ── Voraussetzungen ──────────────────────────────────────────────────

    /**
     * Prüft PHP-Version, benötigte Extensions und Schreibrechte.
     *
     * @return list<array{label: string, ok: bool, hint: string}>
     */
    public function requirements(?string $driver = null): array {
        $checks = [];

        $minPhp = '8.4.0';
        $checks[] = [
            'label' => 'PHP >= ' . $minPhp . ' (' . PHP_VERSION . ')',
            'ok' => version_compare(PHP_VERSION, $minPhp, '>='),
            'hint' => 'PHP-Version aktualisieren.',
        ];

        $extensions = ['pdo', 'mbstring', 'openssl', 'json', 'ctype', 'tokenizer', 'fileinfo'];
        foreach ($extensions as $ext) {
            $checks[] = [
                'label' => 'Extension: ' . $ext,
                'ok' => extension_loaded($ext),
                'hint' => 'PHP-Erweiterung "' . $ext . '" installieren/aktivieren.',
            ];
        }

        foreach ($this->driverExtensions($driver) as $ext) {
            $checks[] = [
                'label' => 'Extension: ' . $ext,
                'ok' => extension_loaded($ext),
                'hint' => 'Datenbank-Treiber "' . $ext . '" installieren/aktivieren.',
            ];
        }

        foreach ($this->writablePaths() as $path) {
            $checks[] = [
                'label' => 'Schreibbar: ' . $this->relative($path),
                'ok' => is_writable($path),
                'hint' => 'Schreibrechte für "' . $this->relative($path) . '" setzen.',
            ];
        }

        return $checks;
    }

    public function requirementsMet(?string $driver = null): bool {
        foreach ($this->requirements($driver) as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    // ── Anwendungs-/APP_KEY-Konfiguration ────────────────────────────────

    /**
     * Setzt grundlegende Anwendungs-Variablen und erzeugt — falls noch nicht
     * vorhanden — einen APP_KEY. Ein bereits gesetzter Key wird NIEMALS
     * überschrieben, da daran verschlüsselte Felder (PluginSetting.settings,
     * SoftwareInstallation.license_key) hängen.
     *
     * @param  array{app_name?: string, app_url?: string, app_env?: string, locale?: string, timezone?: string}  $data
     */
    public function configureApp(array $data): void {
        $this->env->ensureFileExists();

        $values = [];
        if (isset($data['app_name'])) {
            $values['APP_NAME'] = $data['app_name'];
        }
        if (isset($data['app_url'])) {
            $values['APP_URL'] = rtrim($data['app_url'], '/');
        }
        if (isset($data['app_env'])) {
            $values['APP_ENV'] = $data['app_env'];
            $values['APP_DEBUG'] = $data['app_env'] === 'local' ? 'true' : 'false';
        }
        if (isset($data['locale'])) {
            $values['APP_LOCALE'] = $data['locale'];
        }
        if (isset($data['timezone'])) {
            $values['APP_TIMEZONE'] = $data['timezone'];
        }

        if ($values !== []) {
            $this->env->setMany($values);
        }

        $this->ensureAppKey();
    }

    /**
     * Erzeugt einen APP_KEY, sofern noch keiner gesetzt ist. Lädt den Key in
     * die Laufzeit-Config, damit Folge-Schritte (Session, Verschlüsselung)
     * sofort funktionieren.
     *
     * @return bool true, wenn ein neuer Key erzeugt wurde
     */
    public function ensureAppKey(): bool {
        $this->env->ensureFileExists();

        $current = $this->env->get('APP_KEY');
        if (is_string($current) && $current !== '') {
            $this->applyKeyToRuntime($current);

            return false;
        }

        $key = 'base64:' . base64_encode(Encrypter::generateKey($this->cipher()));
        $this->env->set('APP_KEY', $key);
        $this->applyKeyToRuntime($key);

        return true;
    }

    public function hasAppKey(): bool {
        $current = $this->env->get('APP_KEY');

        return is_string($current) && $current !== '';
    }

    // ── Datenbank ────────────────────────────────────────────────────────

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

    public function runMigrations(): void {
        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * Legt Rollen und Permissions an (KEINE Demo-User — anders als der
     * DatabaseSeeder).
     */
    public function seedRolesAndPermissions(): void {
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\PermissionsSeeder::class,
            '--force' => true,
        ]);
    }

    // ── Organisation & Admin ─────────────────────────────────────────────

    /**
     * Legt die erste Organisation samt Admin-Benutzer an. Idempotent in dem
     * Sinne, dass eine bestehende Organisation mit gleichem Slug erweitert
     * statt dupliziert wird.
     *
     * @param  array{org_name: string, name: string, email: string, password: string}  $data
     */
    public function createOrganizationAndAdmin(array $data): User {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::transaction(function () use ($data): User {
            $org = Organization::firstOrCreate(
                ['slug' => Str::slug($data['org_name']) ?: 'default'],
                [
                    'name' => $data['org_name'],
                    'plan' => Organization::PLAN_FREE,
                    'locale' => (string) config('app.locale', 'de'),
                    'timezone' => (string) config('app.timezone', 'Europe/Berlin'),
                    'is_active' => true,
                ],
            );

            /** @var User $user */
            $user = User::create([
                'organization_id' => $org->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            if ($org->owner_id === null) {
                $org->update(['owner_id' => $user->id]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
            $adminRole = Role::findOrCreate(UserRole::Admin->value, 'web');
            $user->assignRole($adminRole);

            return $user;
        });
    }

    // ── Mail / Integrationen ─────────────────────────────────────────────

    /**
     * @param  array<string, string|int|null>  $data
     */
    public function configureMail(array $data): void {
        $this->env->ensureFileExists();
        $values = [];
        foreach (
            [
                'mailer' => 'MAIL_MAILER',
                'host' => 'MAIL_HOST',
                'port' => 'MAIL_PORT',
                'username' => 'MAIL_USERNAME',
                'password' => 'MAIL_PASSWORD',
                'scheme' => 'MAIL_SCHEME',
                'from_address' => 'MAIL_FROM_ADDRESS',
                'from_name' => 'MAIL_FROM_NAME',
            ] as $key => $envKey
        ) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $values[$envKey] = (string) $data[$key];
            }
        }

        if ($values !== []) {
            $this->env->setMany($values);
        }
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function configureIntegrations(array $data): void {
        $this->env->ensureFileExists();
        $values = [];
        foreach (
            [
                'lexoffice_api_key' => 'LEXOFFICE_API_KEY',
                'vapid_public_key' => 'VAPID_PUBLIC_KEY',
                'vapid_private_key' => 'VAPID_PRIVATE_KEY',
                'vapid_subject' => 'VAPID_SUBJECT',
            ] as $key => $envKey
        ) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $values[$envKey] = (string) $data[$key];
            }
        }

        if ($values !== []) {
            $this->env->setMany($values);
        }
    }

    // ── Interne Helfer ───────────────────────────────────────────────────

    /** @return list<string> */
    private function driverExtensions(?string $driver): array {
        return match ($driver) {
            'mysql' => ['pdo_mysql'],
            'pgsql' => ['pdo_pgsql'],
            'sqlite' => ['pdo_sqlite'],
            default => [],
        };
    }

    /** @return list<string> */
    private function writablePaths(): array {
        return [
            base_path('.env'),
            storage_path(),
            storage_path('framework'),
            base_path('bootstrap/cache'),
            database_path(),
        ];
    }

    private function relative(string $path): string {
        $base = base_path();

        return str_starts_with($path, $base) ? ltrim(substr($path, strlen($base)), '/') : $path;
    }

    private function applyKeyToRuntime(string $key): void {
        Config::set('app.key', $key);
        // Encrypter-Singleton neu binden, damit Session-/Cookie-Verschlüsselung
        // den frischen Key sofort verwendet.
        app()->forgetInstance('encrypter');
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

    private function cipher(): string {
        return (string) config('app.cipher', 'AES-256-CBC');
    }
}
