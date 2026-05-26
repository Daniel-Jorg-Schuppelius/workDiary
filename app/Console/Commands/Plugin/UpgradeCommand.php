<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpgradeCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Plugins\{PluginManager, PluginSchemaManager};
use Illuminate\Console\Command;

/**
 * Bringt die Plugin-Schemata auf den aktuellen Stand (führt neue Migrations
 * aus). Idempotent.
 */
class UpgradeCommand extends Command {
    protected $signature = 'plugin:upgrade {plugin? : Plugin-ID. Ohne Argument: alle Plugins, die noch nicht aktuell sind.}';

    protected $description = 'Aktualisiert das Schema eines oder aller Plugins.';

    public function handle(PluginManager $manager, PluginSchemaManager $schema): int {
        $target = $this->argument('plugin');

        $plugins = $target !== null
            ? collect([$manager->find((string) $target)])->filter()
            : $manager->all();

        if ($plugins->isEmpty()) {
            $this->error('Plugin nicht gefunden: ' . (string) $target);

            return self::FAILURE;
        }

        $any = false;
        foreach ($plugins as $plugin) {
            if (! $schema->needsUpgrade($plugin)) {
                $this->line(sprintf('  · %s: bereits aktuell (v%s).', $plugin->id(), $plugin->schemaVersion()));

                continue;
            }
            $schema->upgrade($plugin);
            $this->info(sprintf('  ✓ %s aktualisiert auf v%s.', $plugin->id(), $plugin->schemaVersion()));
            $any = true;
        }

        if (! $any) {
            $this->line('Nichts zu tun.');
        }

        return self::SUCCESS;
    }
}
