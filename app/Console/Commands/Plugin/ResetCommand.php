<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResetCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Models\PluginState;
use App\Plugins\{PluginErrorRecorder, PluginManager};
use Illuminate\Console\Command;

/**
 * Betreiber-Reset für Auto-Disable-Zustände (Review 2026-08, W1b): der
 * globale Kill-Switch (organization_id = null, z. B. Boot-Fehler) gilt
 * instanzweit und ist bewusst NICHT über die Org-Admin-UI aufhebbar.
 */
class ResetCommand extends Command {
    protected $signature = 'plugin:reset
        {plugin : Plugin-ID}
        {--organization= : Nur den Zustand dieser Organisation zurücksetzen}
        {--all : Globalen UND alle Org-Zustände zurücksetzen}';

    protected $description = 'Setzt Failure-Counter und Auto-Disable eines Plugins zurück (Default: globaler Zustand).';

    public function handle(PluginManager $manager, PluginErrorRecorder $recorder): int {
        $pluginId = (string) $this->argument('plugin');
        if ($manager->find($pluginId) === null) {
            $this->error('Plugin nicht gefunden: ' . $pluginId);

            return self::FAILURE;
        }

        if ($this->option('all')) {
            $orgIds = PluginState::query()
                ->where('plugin_id', $pluginId)
                ->pluck('organization_id');
            foreach ($orgIds as $orgId) {
                $recorder->reset($pluginId, $orgId === null ? null : (int) $orgId);
            }
            $recorder->reset($pluginId, null);
            $this->info(sprintf('%s: globaler und %d Org-Zustand/-Zustände zurückgesetzt.', $pluginId, $orgIds->filter()->count()));

            return self::SUCCESS;
        }

        $org = $this->option('organization');
        $orgId = $org !== null ? (int) $org : null;
        $recorder->reset($pluginId, $orgId);
        $this->info(sprintf(
            '%s: %s zurückgesetzt (failure_count = 0, Auto-Disable aufgehoben).',
            $pluginId,
            $orgId === null ? 'globaler Zustand' : "Zustand der Organisation {$orgId}",
        ));

        return self::SUCCESS;
    }
}
