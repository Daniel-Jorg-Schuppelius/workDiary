<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ListCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Models\PluginState;
use App\Plugins\PluginManager;
use Illuminate\Console\Command;

/**
 * Listet alle registrierten Plugins mit Status, installierter Version
 * und letztem Health-Ergebnis. Nützlich für Cron-Mails und Support.
 */
class ListCommand extends Command {
    protected $signature = 'plugin:list';

    protected $description = 'Zeigt alle registrierten Plugins mit Version, Status und Health.';

    public function handle(PluginManager $manager): int {
        // Pro Plugin können mehrere (Org-)Zustände existieren. Für die globale
        // CLI-Übersicht je Plugin den auffälligsten zeigen: auto-disabled zuletzt
        // sortiert → keyBy behält ihn.
        $states = PluginState::all()
            ->sortBy(fn(PluginState $s): int => $s->isAutoDisabled() ? 1 : 0)
            ->keyBy('plugin_id');

        $rows = [];
        foreach ($manager->all() as $plugin) {
            /** @var PluginState|null $state */
            $state = $states->get($plugin->id());
            $rows[] = [
                $plugin->id(),
                $plugin->name(),
                $plugin->version(),
                $state !== null ? ($state->installed_version ?? '—') : '—',
                $plugin->schemaVersion(),
                $state !== null ? ($state->last_health_status ?? '—') : '—',
                $state !== null ? $state->failure_count : 0,
                $state !== null && $state->isAutoDisabled() ? 'auto-disabled' : 'ok',
            ];
        }

        $this->table(
            ['id', 'name', 'version', 'schema (inst.)', 'schema (cur.)', 'health', 'fails', 'state'],
            $rows,
        );

        return self::SUCCESS;
    }
}
