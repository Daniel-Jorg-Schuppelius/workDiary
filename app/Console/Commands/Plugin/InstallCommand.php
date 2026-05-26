<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Plugins\{PluginManager, PluginSchemaManager};
use Illuminate\Console\Command;

/**
 * Führt die initialen Plugin-Migrations für eine bestimmte Plugin-ID aus.
 * Ohne Argument werden alle Plugins berücksichtigt, die `migrationsPath()`
 * liefern und noch nicht installiert sind.
 */
class InstallCommand extends Command {
    protected $signature = 'plugin:install {plugin? : Plugin-ID (z. B. lexoffice). Ohne Argument: alle.}';

    protected $description = 'Installiert das Schema eines Plugins (führt seine Migrations aus).';

    public function handle(PluginManager $manager, PluginSchemaManager $schema): int {
        $target = $this->argument('plugin');

        $plugins = $target !== null
            ? collect([$manager->find((string) $target)])->filter()
            : $manager->all();

        if ($plugins->isEmpty()) {
            $this->error('Plugin nicht gefunden: ' . (string) $target);

            return self::FAILURE;
        }

        foreach ($plugins as $plugin) {
            if ($plugin->migrationsPath() === null) {
                $this->line(sprintf('  - %s: kein eigenes Schema, übersprungen.', $plugin->id()));

                continue;
            }
            $did = $schema->install($plugin);
            $this->info(sprintf('  ✓ %s installiert (v%s)%s', $plugin->id(), $plugin->schemaVersion(), $did ? '' : ' [no-op]'));
        }

        return self::SUCCESS;
    }
}
