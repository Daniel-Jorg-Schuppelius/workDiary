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

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

/**
 * Fassade über die gesamte Installationslogik für den Web-Installer und das
 * begleitende `app:install` Artisan-Command. Die Bausteine liegen seit der
 * God-Klassen-Aufteilung (Refactoring Welle 2, B6b) in
 * {@see AppConfigurator} (APP_-Variablen, APP_KEY, SQIDS_SALT),
 * {@see DatabaseConfigurator} (Verbindungstest/.env/Runtime),
 * {@see MailIntegrationsConfigurator} (MAIL_-Variablen, Lexoffice, VAPID) und
 * {@see OrganizationProvisioner} (Erst-Org + Admin) — der öffentliche
 * Vertrag dieser Fassade ist unverändert.
 *
 * Die Installation gilt als abgeschlossen, sobald die Lock-Datei
 * {@see self::lockPath()} existiert. Solange sie fehlt, leitet
 * {@see \App\Http\Middleware\RedirectIfNotInstalled} auf den Wizard um.
 */
class InstallationManager {
    /** Unterstützte Datenbank-Treiber im Installer. */
    public const DRIVERS = ['sqlite', 'mysql', 'pgsql'];

    private readonly string $lockPath;

    private readonly AppConfigurator $app;

    private readonly DatabaseConfigurator $database;

    private readonly MailIntegrationsConfigurator $mail;

    private readonly OrganizationProvisioner $provisioner;

    public function __construct(private readonly EnvWriter $env, ?string $lockPath = null) {
        $this->lockPath = $lockPath ?? storage_path('installed');
        $this->app = new AppConfigurator($env);
        $this->database = new DatabaseConfigurator($env);
        $this->mail = new MailIntegrationsConfigurator($env);
        $this->provisioner = new OrganizationProvisioner($this->database);
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

        foreach ($this->database->driverExtensions($driver) as $ext) {
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
     * @param  array{app_name?: string, app_url?: string, app_env?: string, locale?: string, timezone?: string}  $data
     *
     * @see AppConfigurator::configureApp()
     */
    public function configureApp(array $data): void {
        $this->app->configureApp($data);
    }

    /**
     * @return bool true, wenn ein neuer Key erzeugt wurde
     *
     * @see AppConfigurator::ensureAppKey()
     */
    public function ensureAppKey(): bool {
        return $this->app->ensureAppKey();
    }

    public function hasAppKey(): bool {
        return $this->app->hasAppKey();
    }

    /**
     * @return bool true, wenn ein neuer Salt erzeugt wurde
     *
     * @see AppConfigurator::ensureSqidsSalt()
     */
    public function ensureSqidsSalt(): bool {
        return $this->app->ensureSqidsSalt();
    }

    /**
     * Erzeugt einen NEUEN SQIDS_SALT und überschreibt einen vorhandenen Wert.
     * Achtung: Dadurch ändern sich ALLE öffentlich sichtbaren Sqid-Route-Keys;
     * bereits verteilte URLs werden ungültig.
     */
    public function regenerateSqidsSalt(): void {
        $this->app->regenerateSqidsSalt();
    }

    public function hasSqidsSalt(): bool {
        return $this->app->hasSqidsSalt();
    }

    // ── Datenbank ────────────────────────────────────────────────────────

    /**
     * @param  array<string, string|int|null>  $config
     *
     * @see DatabaseConfigurator::testConnection()
     */
    public function testConnection(array $config): bool {
        return $this->database->testConnection($config);
    }

    /**
     * @param  array<string, string|int|null>  $config
     *
     * @see DatabaseConfigurator::configureDatabase()
     */
    public function configureDatabase(array $config): void {
        $this->database->configureDatabase($config);
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
     * @param  array{org_name: string, name: string, email: string, password: string}  $data
     * @param  bool  $platformAdmin  Erst-Betreiber (darf Org-Kontext wechseln).
     *         Installer setzen true; app:admin nur mit --platform.
     *
     * @see OrganizationProvisioner::createOrganizationAndAdmin()
     */
    public function createOrganizationAndAdmin(array $data, bool $platformAdmin = true): User {
        return $this->provisioner->createOrganizationAndAdmin($data, $platformAdmin);
    }

    /**
     * @see OrganizationProvisioner::resetAdminPassword()
     */
    public function resetAdminPassword(string $email, string $password): User {
        return $this->provisioner->resetAdminPassword($email, $password);
    }

    // ── Mail / Integrationen ─────────────────────────────────────────────

    /**
     * @param  array<string, string|int|null>  $data
     *
     * @see MailIntegrationsConfigurator::configureMail()
     */
    public function configureMail(array $data): void {
        $this->mail->configureMail($data);
    }

    /**
     * @param  array<string, string|null>  $data
     *
     * @see MailIntegrationsConfigurator::configureIntegrations()
     */
    public function configureIntegrations(array $data): void {
        $this->mail->configureIntegrations($data);
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     *
     * @see MailIntegrationsConfigurator::generateVapidKeys()
     */
    public function generateVapidKeys(): array {
        return $this->mail->generateVapidKeys();
    }

    // ── Interne Helfer ───────────────────────────────────────────────────

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
}
