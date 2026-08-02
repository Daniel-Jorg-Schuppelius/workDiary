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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Log};
use Throwable;

/**
 * Zentrale Senke für Plugin-Fehler (gehärtet: Review 2026-08, W2).
 *
 *  - Persistiert Fehler dedupliziert in `plugin_errors`: Wiederholungen mit
 *    gleichem Hash inkrementieren die offene Zeile (occurrences) statt die
 *    Inbox zu fluten; der Stacktrace wird nur beim ersten Auftreten gespeichert.
 *  - Erhöht `plugin_states.failure_count` innerhalb eines Zeitfensters
 *    (`plugins.auto_disable_window_hours`); ein abgelaufenes Fenster startet
 *    die Zählung neu.
 *  - Bei Überschreiten der Schwelle (`plugins.auto_disable_threshold`, für die
 *    Boot-Phase optional `plugins.auto_disable_boot_threshold`) wird
 *    `disabled_reason` gesetzt; das Event feuert erst nach Commit.
 *  - Phase `manual` (Admin-Button) wird aufgezeichnet, zählt aber nie (E-1).
 */
class PluginErrorRecorder {
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $pluginId, string $phase, Throwable $e, array $context = [], ?int $organizationId = null): PluginError {
        // Dedup-Fingerprint (kein Security-Kontext) — Toolkit-first (C1).
        $hash = (string) \CommonToolkit\Helper\Data\CryptoHelper::hash(implode('|', [
            $pluginId,
            (string) ($organizationId ?? 'global'),
            $phase,
            $e::class,
            mb_substr($e->getMessage(), 0, 500),
        ]));

        $error = $this->withUniqueRetry(fn(): PluginError => DB::transaction(function () use ($pluginId, $phase, $e, $context, $organizationId, $hash): PluginError {
            $error = $this->persistError($pluginId, $phase, $e, $context, $organizationId, $hash);

            // Manuelle Admin-Checks: Inbox ja, Auto-Disable-Zählung nein (E-1).
            if ($phase !== PluginError::PHASE_MANUAL) {
                $this->countFailure($pluginId, $phase, $e, $organizationId);
            }

            return $error;
        }));

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

    /**
     * @param  array<string, mixed>  $context
     */
    private function persistError(string $pluginId, string $phase, Throwable $e, array $context, ?int $organizationId, string $hash): PluginError {
        // Dedup (W2c): dieselbe offene Störung inkrementiert statt neuer Zeile.
        $existing = PluginError::query()
            ->where('error_hash', $hash)
            ->whereNull('acknowledged_at')
            ->orderByDesc('occurred_at')
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            $existing->occurrences = (int) $existing->occurrences + 1;
            $existing->last_occurred_at = now();
            $existing->save();

            return $existing;
        }

        $error = new PluginError([
            'plugin_id' => $pluginId,
            'organization_id' => $organizationId,
            'phase' => $phase,
            'exception_class' => $e::class,
            'message' => $this->truncate($e->getMessage(), 2000),
            'trace' => $e->getTraceAsString(),
            'context' => $context,
            'error_hash' => $hash,
            'occurrences' => 1,
            'occurred_at' => now(),
            'last_occurred_at' => now(),
        ]);
        $error->save();

        return $error;
    }

    private function countFailure(string $pluginId, string $phase, Throwable $e, ?int $organizationId): void {
        // Zustand/Auto-Disable wird pro (Plugin, Organisation) geführt: ein
        // fehlerhafter Key in Org A legt das Plugin nicht für Org B still.
        $state = $this->lockedState($pluginId, $organizationId);

        // Zeitfenster (W2b): außerhalb des Fensters beginnt die Zählung neu.
        $windowHours = (int) config('plugins.auto_disable_window_hours', 24);
        $windowStart = $state->failure_window_started_at;
        if ($windowHours > 0 && ($windowStart === null || $windowStart->lt(now()->subHours($windowHours)))) {
            $state->failure_count = 0;
            $state->failure_window_started_at = now();
        } elseif ($windowStart === null) {
            $state->failure_window_started_at = now();
        }
        $state->failure_count = (int) $state->failure_count + 1;

        $threshold = (int) config('plugins.auto_disable_threshold', 5);
        if ($phase === PluginError::PHASE_BOOT) {
            $bootThreshold = config('plugins.auto_disable_boot_threshold');
            $threshold = $bootThreshold !== null ? (int) $bootThreshold : $threshold;
        }

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
            // Erst nach Commit (C5): der synchrone Listener erzeugt eine
            // kritische Betriebsaufgabe — nicht bevor der State geschrieben ist.
            $reason = (string) $state->disabled_reason;
            $failures = (int) $state->failure_count;
            DB::afterCommit(static function () use ($pluginId, $organizationId, $reason, $failures): void {
                \App\Events\PluginAutoDisabled::dispatch($pluginId, $organizationId, $reason, $failures);
            });
        }
        $state->save();
        $this->flushManagerCaches();
    }

    /** Setzt failure_count + disabled_reason zurück (Admin-Aktion „Reset"). */
    public function reset(string $pluginId, ?int $organizationId = null): void {
        $this->clearFailures($pluginId, $organizationId);
    }

    /** Nach einem erfolgreichen Healthcheck → Failure-Counter wieder auf 0. */
    public function markHealthy(string $pluginId, ?int $organizationId = null): void {
        $this->clearFailures($pluginId, $organizationId);
    }

    /**
     * Gemeinsame Implementierung (W2f): Counter/Fenster/Auto-Disable löschen.
     * Recovery unabhängig vom Health-Status-Übergang (A6): war das Plugin
     * auto-deaktiviert, feuert PluginRecovered, damit die kritische
     * Betriebsaufgabe geschlossen wird (der Task-Resolve ist idempotent).
     */
    private function clearFailures(string $pluginId, ?int $organizationId): void {
        $wasAutoDisabled = $this->withUniqueRetry(function () use ($pluginId, $organizationId): bool {
            return DB::transaction(function () use ($pluginId, $organizationId): bool {
                $state = $this->lockedState($pluginId, $organizationId);
                $wasAutoDisabled = $state->isAutoDisabled();
                $state->failure_count = 0;
                $state->failure_window_started_at = null;
                $state->disabled_reason = null;
                $state->save();

                return $wasAutoDisabled;
            });
        });
        $this->flushManagerCaches();

        if ($wasAutoDisabled) {
            \App\Events\PluginRecovered::dispatch($pluginId, $organizationId, 'auto-disable aufgehoben');
        }
    }

    /** Gesperrte (bzw. neue) Zustandszeile für (Plugin, Organisation). */
    private function lockedState(string $pluginId, ?int $organizationId): PluginState {
        $state = PluginState::query()
            ->where('plugin_id', $pluginId)
            ->when(
                $organizationId === null,
                fn($q) => $q->whereNull('organization_id'),
                fn($q) => $q->where('organization_id', $organizationId),
            )
            ->lockForUpdate()
            ->first();

        if ($state !== null) {
            return $state;
        }

        $state = new PluginState;
        $state->plugin_id = $pluginId;
        $state->organization_id = $organizationId;

        return $state;
    }

    /**
     * Insert-Rennen auf die Unique-Indizes lösen sich per Retry: der zweite
     * Versuch findet die inzwischen vorhandene Zeile und aktualisiert sie (W2a).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $fn
     * @return TReturn
     */
    private function withUniqueRetry(callable $fn): mixed {
        try {
            return $fn();
        } catch (QueryException) {
            return $fn();
        }
    }

    /** Memoisierte enabled()/autoDisabled-Sicht invalidieren (W2e). */
    private function flushManagerCaches(): void {
        try {
            if (app()->resolved(PluginManager::class)) {
                app(PluginManager::class)->flushRuntimeCaches();
            }
        } catch (Throwable) {
            // Cache-Invalidierung darf nie werfen.
        }
    }

    private function truncate(string $value, int $max): string {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1) . '…';
    }
}
