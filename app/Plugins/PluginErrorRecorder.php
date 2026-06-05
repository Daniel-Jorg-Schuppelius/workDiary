<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginErrorRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Models\{PluginError, PluginState};
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zentrale Senke für Plugin-Fehler.
 *
 *  - Persistiert jeden Fehler in `plugin_errors` (für Inbox-UI).
 *  - Erhöht `plugin_states.failure_count`.
 *  - Bei Überschreiten der Schwelle aus `config('plugins.auto_disable_threshold')`
 *    wird `plugin_states.disabled_reason` gesetzt → Plugin ist global stumm
 *    geschaltet, bis ein Admin den Reset auslöst.
 *  - Loggt zusätzlich via Log-Facade (für Sentry/Stack-Trace-Sammler).
 */
class PluginErrorRecorder {
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $pluginId, string $phase, Throwable $e, array $context = [], ?int $organizationId = null): PluginError {
        $error = new PluginError([
            'plugin_id' => $pluginId,
            'organization_id' => $organizationId,
            'phase' => $phase,
            'exception_class' => $e::class,
            'message' => $this->truncate($e->getMessage(), 2000),
            'trace' => $e->getTraceAsString(),
            'context' => $context,
            'occurred_at' => now(),
        ]);
        $error->save();

        // Zustand/Auto-Disable wird pro (Plugin, Organisation) geführt: ein
        // fehlerhafter Key in Org A legt das Plugin nicht für Org B still.
        $state = PluginState::findOrInit($pluginId, $organizationId);
        $state->plugin_id = $pluginId;
        $state->organization_id = $organizationId;
        $state->failure_count = (int) $state->failure_count + 1;

        $threshold = (int) config('plugins.auto_disable_threshold', 5);
        if ($threshold > 0 && $state->failure_count >= $threshold && ! $state->isAutoDisabled()) {
            $state->disabled_reason = sprintf(
                'Auto-Disable nach %d Fehler(n) (zuletzt %s in %s)',
                $state->failure_count,
                $e::class,
                $phase,
            );
            Log::warning('Plugin auto-disabled after repeated failures', [
                'plugin_id' => $pluginId,
                'organization_id' => $organizationId,
                'failure_count' => $state->failure_count,
                'phase' => $phase,
            ]);
        }
        $state->save();

        Log::error('Plugin error recorded', [
            'plugin_id' => $pluginId,
            'organization_id' => $organizationId,
            'phase' => $phase,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'context' => $context,
        ]);

        return $error;
    }

    /** Setzt failure_count + disabled_reason zurück (Admin-Aktion „Reset"). */
    public function reset(string $pluginId, ?int $organizationId = null): void {
        $state = PluginState::findOrInit($pluginId, $organizationId);
        $state->plugin_id = $pluginId;
        $state->organization_id = $organizationId;
        $state->failure_count = 0;
        $state->disabled_reason = null;
        $state->save();
    }

    /** Nach einem erfolgreichen Healthcheck → Failure-Counter wieder auf 0. */
    public function markHealthy(string $pluginId, ?int $organizationId = null): void {
        $state = PluginState::findOrInit($pluginId, $organizationId);
        $state->plugin_id = $pluginId;
        $state->organization_id = $organizationId;
        $state->failure_count = 0;
        $state->disabled_reason = null;
        $state->save();
    }

    private function truncate(string $value, int $max): string {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1) . '…';
    }
}
