<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UninstallCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Plugins\{PluginManager, PluginSchemaManager};
use Illuminate\Console\{Command, ConfirmableTrait};

/**
 * Rollt die Plugin-eigenen Migrations zurück. Zerstörerisch — verlangt
 * `--force` (oder eine Bestätigung in interaktiven Sessions).
 */
class UninstallCommand extends Command {
    use ConfirmableTrait;

    protected $signature = 'plugin:uninstall {plugin : Plugin-ID} {--force : Ohne Rückfrage ausführen}';

    protected $description = 'Deinstalliert ein Plugin-Schema (Migrations werden rollbackt).';

    public function handle(PluginManager $manager, PluginSchemaManager $schema): int {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $pluginId = (string) $this->argument('plugin');
        $plugin = $manager->find($pluginId);
        if ($plugin === null) {
            $this->error('Plugin nicht gefunden: ' . $pluginId);

            return self::FAILURE;
        }

        if ($plugin->migrationsPath() === null) {
            $this->line('Plugin liefert kein Schema, nichts zu tun.');

            return self::SUCCESS;
        }

        $schema->uninstall($plugin);
        $this->info('  ✓ ' . $pluginId . ' deinstalliert.');

        return self::SUCCESS;
    }
}
