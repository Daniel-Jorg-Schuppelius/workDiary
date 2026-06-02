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
use Minishlink\WebPush\VAPID;
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

    /**
     * Entfernt den Installations-Marker, sodass der Wizard erneut durchlaufen
     * werden kann. Wirft, wenn die vorhandene Datei nicht gelöscht werden kann.
     *
     * @return bool true, wenn ein Marker entfernt wurde; false, wenn keiner existierte
     */
    public function markUninstalled(): bool {
        if (! is_file($this->lockPath)) {
            return false;
        }

        if (! @unlink($this->lockPath)) {
            throw new RuntimeException('Konnte Installations-Marker nicht entfernen: ' . $this->lockPath);
        }

        return true;
    }

    /**
     * Verwirft die gecachten Bootstrap-Dateien (Config, Routen, Events, Views).
     * Notwendig nach Abschluss des Installers, weil die während des Wizards in
     * die .env geschriebenen Werte (DB, Mail, Lexoffice-API-Key, VAPID …) sonst
     * von einem zuvor erstellten `config:cache` verdeckt werden und im
     * laufenden Betrieb „nicht gespeichert" wirken.
     */
    public function clearCaches(): void {
        foreach (['config:clear', 'route:clear', 'event:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // Best effort: ein fehlender Cache (z. B. route:clear ohne
                // Cache-Datei) darf den Abschluss nicht verhindern.
            }
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
        $this->ensureSqidsSalt();
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

    /**
     * Erzeugt einen SQIDS_SALT, sofern noch keiner gesetzt ist. Der Salt geht
     * in die Permutation des Sqids-Alphabets ein; ohne ihn verweigert der
     * SqidEncoder in Produktion den Dienst (RuntimeException). Ein bereits
     * gesetzter Salt wird NIEMALS überschrieben, da daran die öffentlich
     * sichtbaren Route-Keys (Sqids) hängen.
     *
     * @return bool true, wenn ein neuer Salt erzeugt wurde
     */
    public function ensureSqidsSalt(): bool {
        $this->env->ensureFileExists();

        $current = $this->env->get('SQIDS_SALT');
        if (is_string($current) && $current !== '') {
            Config::set('sqids.salt', $current);

            return false;
        }

        return $this->writeSqidsSalt();
    }

    /**
     * Erzeugt einen NEUEN SQIDS_SALT und überschreibt einen vorhandenen Wert.
     * Achtung: Dadurch ändern sich ALLE öffentlich sichtbaren Sqid-Route-Keys;
     * bereits verteilte URLs werden ungültig.
     */
    public function regenerateSqidsSalt(): void {
        $this->env->ensureFileExists();
        $this->writeSqidsSalt();
    }

    private function writeSqidsSalt(): bool {
        $salt = bin2hex(random_bytes(32));
        $this->env->set('SQIDS_SALT', $salt);
        Config::set('sqids.salt', $salt);
        // SqidEncoder-Singleton neu binden, damit der frische Salt sofort greift.
        app()->forgetInstance(\App\Services\SqidEncoder::class);

        return true;
    }

    public function hasSqidsSalt(): bool {
        $current = $this->env->get('SQIDS_SALT');

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

    public function runMigrations(bool $fresh = false): void {
        // Migrationen (insbesondere migrate:fresh über viele Tabellen) können auf
        // langsamen Shared-Hostings länger dauern als das Standard-PHP-Limit.
        $this->extendExecutionTime();

        if ($fresh) {
            // Verwirft alle vorhandenen Tabellen und migriert von Grund auf neu.
            // Nützlich, wenn eine frühere Migration abgebrochen wurde und Tabellen
            // ohne passenden Migrations-Eintrag zurückblieben.
            Artisan::call('migrate:fresh', ['--force' => true]);

            return;
        }

        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * Hebt das Zeit- und Speicherlimit für langlaufende Installationsschritte
     * an, soweit die Hosting-Umgebung das zulässt (ignoriert Fehler still).
     */
    private function extendExecutionTime(int $seconds = 300): void {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
    }

    /**
     * Legt Rollen und Permissions an (KEINE Demo-User — anders als der
     * DatabaseSeeder).
     */
    public function seedRolesAndPermissions(): void {
        $this->extendExecutionTime();

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
        // Dieser Schritt läuft in einem eigenen HTTP-Request, in dem die
        // (ggf. gecachte) Config noch auf die alte Verbindung zeigen kann.
        // Daher die in der .env hinterlegte DB-Verbindung erneut aktivieren,
        // damit der Admin garantiert in der konfigurierten Datenbank landet.
        $this->applyConfiguredDatabaseToRuntime();

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

    /**
     * Setzt das Passwort eines bestehenden Benutzers neu und stellt sicher,
     * dass er die Admin-Rolle seiner Organisation besitzt. Reaktiviert vorher
     * die in der .env konfigurierte DB-Verbindung, damit auch bei gecachter
     * Config die richtige Datenbank getroffen wird.
     */
    public function resetAdminPassword(string $email, string $password): User {
        $this->applyConfiguredDatabaseToRuntime();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::transaction(function () use ($email, $password): User {
            /** @var User|null $user */
            $user = User::where('email', $email)->first();
            if ($user === null) {
                throw new RuntimeException("Kein Benutzer mit E-Mail {$email} gefunden.");
            }

            // Cast 'password' => 'hashed' übernimmt das Hashing beim Speichern.
            $user->password = $password;
            $user->save();

            if ($user->organization_id !== null) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
            }
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

    /**
     * Erzeugt ein neues VAPID-Schlüsselpaar für Web-Push. Die Schlüssel werden
     * NICHT persistiert – das übernimmt der Integrations-Schritt, sobald der
     * Anwender das Formular absendet.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    public function generateVapidKeys(): array {
        /** @var array{publicKey: string, privateKey: string} $keys */
        $keys = VAPID::createVapidKeys();

        return $keys;
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
     * Liest die in der .env hinterlegte Datenbank-Konfiguration und aktiviert
     * sie für die laufende Runtime. Nötig in Wizard-Schritten nach dem
     * Datenbank-Schritt, die in eigenen Requests laufen und sonst eine
     * (gecachte) Alt-Verbindung verwenden würden.
     */
    private function applyConfiguredDatabaseToRuntime(): void {
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

    private function cipher(): string {
        return (string) config('app.cipher', 'AES-256-CBC');
    }
}
