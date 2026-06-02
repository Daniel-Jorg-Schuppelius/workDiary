<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Install\InstallationManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\{confirm, password, select, text};

use Throwable;

/**
 * CLI-Pendant zum Web-Installer. Führt interaktiv durch dieselben Schritte und
 * nutzt denselben {@see InstallationManager}.
 */
class InstallCommand extends Command {
    protected $signature = 'app:install {--force : Vorhandene Installation überschreiben}';

    protected $description = 'Richtet die Anwendung interaktiv ein (APP_KEY, Datenbank, Admin, Mail, Integrationen).';

    public function handle(): int {
        $installer = InstallationManager::make();

        if ($installer->isInstalled() && ! $this->option('force')) {
            $this->error('Die Anwendung ist bereits installiert. Mit --force erneut ausführen.');

            return self::FAILURE;
        }

        $this->info('WorkDiary-Installation');
        $this->newLine();

        // 1) Voraussetzungen
        $driver = (string) select(
            label: 'Datenbank-Treiber',
            options: InstallationManager::DRIVERS,
            default: 'sqlite',
        );

        $this->line('Prüfe Systemvoraussetzungen …');
        $failed = array_filter($installer->requirements($driver), static fn(array $c): bool => ! $c['ok']);
        foreach ($installer->requirements($driver) as $check) {
            $this->line(($check['ok'] ? '<info>✓</info> ' : '<error>✗</error> ') . $check['label']);
        }
        if ($failed !== []) {
            $this->error('Bitte beheben Sie die fehlenden Voraussetzungen und versuchen Sie es erneut.');

            return self::FAILURE;
        }

        // 2) Anwendung & APP_KEY
        $installer->configureApp([
            'app_name' => (string) text('Anwendungsname', default: 'WorkDiary'),
            'app_url' => (string) text('Anwendungs-URL', default: 'http://localhost'),
            'app_env' => (string) select('Umgebung', ['production', 'local'], default: 'production'),
            'locale' => (string) text('Sprache (locale)', default: 'de'),
            'timezone' => (string) text('Zeitzone', default: 'Europe/Berlin'),
        ]);
        $this->info('✓ APP_KEY sichergestellt & Anwendungseinstellungen gespeichert.');

        // 3) Datenbank
        $dbConfig = $this->askDatabase($driver);
        if (! $installer->testConnection($dbConfig)) {
            $this->error('Datenbankverbindung fehlgeschlagen.');

            return self::FAILURE;
        }

        try {
            $installer->configureDatabase($dbConfig);
            $this->line('Führe Migrationen aus …');
            $installer->runMigrations();
            $installer->seedRolesAndPermissions();
        } catch (Throwable $e) {
            $this->error('Migration fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }
        $this->info('✓ Datenbank konfiguriert & migriert.');

        // 4) Administrator
        try {
            $user = $installer->createOrganizationAndAdmin([
                'org_name' => (string) text('Name der Organisation', required: true),
                'name' => (string) text('Name des Administrators', required: true),
                'email' => (string) text('E-Mail des Administrators', required: true),
                'password' => (string) password('Passwort', required: true),
            ]);
        } catch (Throwable $e) {
            $this->error('Admin-Anlage fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }
        $this->info('✓ Administrator angelegt: ' . $user->email);

        // 5) Mail (optional)
        if (confirm('E-Mail/SMTP jetzt konfigurieren?', default: false)) {
            $installer->configureMail([
                'mailer' => (string) select('Mailer', ['log', 'smtp'], default: 'log'),
                'host' => (string) text('SMTP-Host', default: '127.0.0.1'),
                'port' => (string) text('Port', default: '587'),
                'username' => (string) text('Benutzer', default: ''),
                'password' => (string) password('Passwort'),
                'from_address' => (string) text('Absender-Adresse', default: 'hello@example.com'),
                'from_name' => (string) text('Absender-Name', default: 'WorkDiary'),
            ]);
            $this->info('✓ E-Mail-Einstellungen gespeichert.');
        }

        // 6) Integrationen (optional)
        if (confirm('Integrationen (Lexoffice/VAPID) jetzt konfigurieren?', default: false)) {
            $installer->configureIntegrations([
                'lexoffice_api_key' => (string) text('Lexoffice API-Schlüssel', default: ''),
                'vapid_subject' => (string) text('VAPID Subject', default: 'mailto:admin@example.com'),
                'vapid_public_key' => (string) text('VAPID Public Key', default: ''),
                'vapid_private_key' => (string) text('VAPID Private Key', default: ''),
            ]);
            $this->info('✓ Integrationen gespeichert.');
        }

        // 7) Abschluss
        $installer->markInstalled();
        $this->newLine();
        $this->info('Installation abgeschlossen. Sie können sich nun anmelden.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string|int|null>
     */
    private function askDatabase(string $driver): array {
        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => (string) text('SQLite-Dateipfad', default: database_path('database.sqlite')),
            ];
        }

        return [
            'driver' => $driver,
            'host' => (string) text('Host', default: '127.0.0.1'),
            'port' => (string) text('Port', default: $driver === 'pgsql' ? '5432' : '3306'),
            'database' => (string) text('Datenbankname', required: true),
            'username' => (string) text('Benutzer', required: true),
            'password' => (string) password('Passwort'),
        ];
    }
}
