<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HealthCheckCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Models\PluginState;
use App\Plugins\{PluginErrorRecorder, PluginHealth, PluginManager};
use Illuminate\Console\Command;
use Throwable;

/**
 * Ruft den `healthCheck()` jedes (oder eines) Plugins auf, persistiert das
 * Ergebnis in `plugin_states` und meldet Fehler an den
 * {@see PluginErrorRecorder} (Phase: healthcheck).
 */
class HealthCheckCommand extends Command {
    protected $signature = 'plugin:healthcheck
        {plugin? : Plugin-ID. Ohne Argument: alle aktiven Plugins.}
        {--no-fail : Auch bei ungesunden Plugins mit Exit 0 beenden (für geplante Läufe — Ergebnis wird trotzdem aufgezeichnet).}';

    protected $description = 'Führt Healthchecks für ein oder alle Plugins durch und persistiert das Ergebnis.';

    public function handle(PluginManager $manager, PluginErrorRecorder $recorder): int {
        $target = $this->argument('plugin');

        $plugins = $target !== null
            ? collect([$manager->find((string) $target)])->filter()
            : $manager->all();

        if ($plugins->isEmpty()) {
            $this->error('Plugin nicht gefunden: ' . (string) $target);

            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;
        foreach ($plugins as $plugin) {
            $state = PluginState::findOrInit($plugin->id());
            $state->plugin_id = $plugin->id();
            $state->last_health_check_at = now();

            try {
                $health = $plugin->healthCheck();
                $state->last_health_status = $health->status;
                $state->last_health_message = $health->message;
                $state->save();

                if ($health->isOk()) {
                    $recorder->markHealthy($plugin->id());
                    $this->line(sprintf('  ✓ %s: ok %s', $plugin->id(), $health->message));
                } elseif ($health->isFailing()) {
                    $this->warn(sprintf('  ✗ %s: failing — %s', $plugin->id(), $health->message));
                    $recorder->record($plugin->id(), 'healthcheck', new \RuntimeException($health->message !== '' ? $health->message : 'failing healthcheck'));
                    $exitCode = self::FAILURE;
                } else {
                    $this->line(sprintf('  ~ %s: degraded — %s', $plugin->id(), $health->message));
                }
            } catch (Throwable $e) {
                $state->last_health_status = PluginHealth::STATUS_FAILING;
                $state->last_health_message = $e->getMessage();
                $state->save();
                $recorder->record($plugin->id(), 'healthcheck', $e);
                $this->error(sprintf('  ✗ %s: exception — %s', $plugin->id(), $e->getMessage()));
                $exitCode = self::FAILURE;
            }
        }

        // Bei `--no-fail` (geplante Läufe) zählt nur, dass die Checks liefen und
        // die Ergebnisse persistiert/aufgezeichnet wurden — ein ungesundes Plugin
        // ist ein erfasster Zustand, kein Kommando-Fehlschlag. So erzeugt der
        // Scheduler keinen irreführenden „failed"-Eintrag, der Auto-Disable-Zähler
        // und die Statusanzeige bleiben aber unberührt.
        if ($this->option('no-fail')) {
            return self::SUCCESS;
        }

        return $exitCode;
    }
}
