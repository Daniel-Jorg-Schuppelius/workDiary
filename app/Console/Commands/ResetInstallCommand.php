<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResetInstallCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Install\InstallationManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\confirm;

/**
 * Setzt den Installationsstatus zurück: entfernt den Lock-Marker
 * (storage/installed) und leert die Caches, sodass der Web-Installer
 * (/install) erneut durchlaufen werden kann. Verändert standardmäßig KEINE
 * Daten – die Datenbank bleibt unangetastet.
 */
class ResetInstallCommand extends Command {
    protected $signature = 'app:reset-install
        {--fresh : Datenbank zusätzlich komplett leeren (migrate:fresh)}
        {--force : Sicherheitsabfragen überspringen}';

    protected $description = 'Setzt den Installationsstatus zurück, damit der Setup-Wizard erneut läuft.';

    public function handle(InstallationManager $installer): int {
        $fresh = (bool) $this->option('fresh');
        $force = (bool) $this->option('force');

        if (! $force && ! confirm('Installationsstatus zurücksetzen und Wizard erneut freischalten?', default: false)) {
            $this->info('Abgebrochen.');

            return self::SUCCESS;
        }

        // 1) Lock-Marker entfernen
        $removed = $installer->markUninstalled();
        $this->line($removed
            ? '✓ Installations-Marker entfernt: ' . $installer->lockPath()
            : 'ℹ Kein Installations-Marker vorhanden.');

        // 2) Optional die Datenbank komplett leeren
        if ($fresh) {
            if (! $force && ! confirm('ALLE Tabellen verwerfen und neu migrieren (migrate:fresh)?', default: false)) {
                $this->warn('Datenbank NICHT geleert.');
            } else {
                $this->line('Leere Datenbank (migrate:fresh) …');
                Artisan::call('migrate:fresh', ['--force' => true], $this->output);
                $this->info('✓ Datenbank neu migriert.');
            }
        }

        // 3) Caches leeren, damit app.installed nicht aus dem Config-Cache kommt
        $this->line('Leere Caches …');
        Artisan::call('optimize:clear', [], $this->output);

        $this->newLine();
        $this->info('Zurückgesetzt. Der Setup-Wizard ist nun unter /install erreichbar.');

        return self::SUCCESS;
    }
}
