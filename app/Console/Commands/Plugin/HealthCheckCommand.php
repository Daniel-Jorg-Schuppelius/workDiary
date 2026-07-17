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

use App\Console\Concerns\IteratesOrganizations;
use App\Events\{PluginHealthChanged, PluginRecovered};
use App\Models\{Organization, PluginState};
use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginCompatibility, PluginErrorRecorder, PluginHealth, PluginManager};
use Illuminate\Console\Command;
use Throwable;

/**
 * Ruft den `healthCheck()` jedes (oder eines) Plugins auf, persistiert das
 * Ergebnis in `plugin_states` und meldet Fehler an den
 * {@see PluginErrorRecorder} (Phase: healthcheck).
 */
class HealthCheckCommand extends Command {
    use IteratesOrganizations;

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
            if ($plugin->isPerOrganization()) {
                // Per-Org-Plugin: je Organisation mit gebundenem Kontext prüfen (jeweils gespeicherter Schlüssel).
                foreach (Organization::query()->get() as $org) {
                    $this->withOrganizationContext($org, function (Organization $org) use ($plugin, $recorder, &$exitCode): void {
                        if (! $plugin->isEnabled()) {
                            return; // in dieser Org nicht aktiv → kein Check
                        }
                        if ($this->checkOne($plugin, (int) $org->id, (string) $org->name, $recorder) === self::FAILURE) {
                            $exitCode = self::FAILURE;
                        }
                    });
                }
            } else {
                // Globales Plugin: einmalig ohne Org-Kontext.
                app()->forgetInstance('currentOrganization');
                if ($this->checkOne($plugin, null, null, $recorder) === self::FAILURE) {
                    $exitCode = self::FAILURE;
                }
            }
        }

        // Bei `--no-fail` (geplante Läufe) zählt nur, dass die Checks liefen — ein ungesundes Plugin ist
        // ein erfasster Zustand, kein Kommando-Fehlschlag (kein irreführender „failed"; Auto-Disable bleibt).
        if ($this->option('no-fail')) {
            return self::SUCCESS;
        }

        return $exitCode;
    }

    /**
     * Führt einen Healthcheck für genau ein (Plugin, Organisation)-Paar aus,
     * persistiert den Zustand und meldet Fehler org-bezogen. `$organizationId`
     * = null → globaler Zustand.
     */
    private function checkOne(Plugin $plugin, ?int $organizationId, ?string $orgName, PluginErrorRecorder $recorder): int {
        $label = $plugin->id() . ($orgName !== null ? " [{$orgName}]" : '');

        $state = PluginState::findOrInit($plugin->id(), $organizationId);
        $previous = $state->last_health_status; // für Übergangs-Erkennung
        $state->plugin_id = $plugin->id();
        $state->organization_id = $organizationId;
        $state->last_health_check_at = now();

        // Kompatibilitätsprüfung VOR dem Healthcheck: inkompatibles Plugin gilt als failing (Auto-Disable), ohne Remote-Zugriff.
        $compat = PluginCompatibility::for($plugin);
        if (! $compat->compatible) {
            $state->last_health_status = PluginHealth::STATUS_FAILING;
            $state->last_health_message = $compat->message;
            $state->save();
            $this->announce($plugin->id(), $organizationId, $previous, PluginHealth::STATUS_FAILING, $compat->message);
            $recorder->record($plugin->id(), 'compatibility', new \RuntimeException($compat->message), [
                'min_app_version' => $compat->minAppVersion,
                'max_app_version' => $compat->maxAppVersion,
                'app_version' => $compat->appVersion,
            ], $organizationId);
            $this->warn(sprintf('  ✗ %s: inkompatibel — %s', $label, $compat->message));

            return self::FAILURE;
        }

        try {
            $startedAt = hrtime(true);
            $health = $plugin->healthCheck();
            $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $state->last_health_status = $health->status;
            $state->last_health_message = $health->message;
            if ($health->isOk()) {
                $state->last_ok_at = now();
            }
            $state->save();
            $this->announce($plugin->id(), $organizationId, $previous, $health->status, $health->message);

            if ($health->isOk()) {
                $recorder->markHealthy($plugin->id(), $organizationId);
                $this->line(sprintf('  ✓ %s: ok %s (%dms)', $label, $health->message, $latencyMs));

                return self::SUCCESS;
            }
            if ($health->isFailing()) {
                $this->warn(sprintf('  ✗ %s: failing — %s (%dms)', $label, $health->message, $latencyMs));
                $recorder->record($plugin->id(), 'healthcheck', new \RuntimeException($health->message !== '' ? $health->message : 'failing healthcheck'), [], $organizationId);

                return self::FAILURE;
            }
            $this->line(sprintf('  ~ %s: degraded — %s (%dms)', $label, $health->message, $latencyMs));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $state->last_health_status = PluginHealth::STATUS_FAILING;
            $state->last_health_message = $e->getMessage();
            $state->save();
            $this->announce($plugin->id(), $organizationId, $previous, PluginHealth::STATUS_FAILING, $e->getMessage());
            $recorder->record($plugin->id(), 'healthcheck', $e, [], $organizationId);
            $this->error(sprintf('  ✗ %s: exception — %s', $label, $e->getMessage()));

            return self::FAILURE;
        }
    }

    /** Feuert Status-Übergangs-Events (kein Spam: nur bei tatsächlicher Änderung). */
    private function announce(string $pluginId, ?int $organizationId, ?string $from, string $to, string $message): void {
        if ($from === $to) {
            return;
        }
        PluginHealthChanged::dispatch($pluginId, $organizationId, $from, $to, $message);
        if ($to === PluginHealth::STATUS_OK && $from !== null) {
            PluginRecovered::dispatch($pluginId, $organizationId, $message);
        }
    }
}
