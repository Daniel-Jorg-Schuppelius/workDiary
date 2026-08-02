<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealthService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Events\{PluginHealthChanged, PluginRecovered};
use App\Models\{PluginError, PluginState};
use App\Plugins\Contracts\Plugin;
use Throwable;

/**
 * Die eine Health-Pipeline (Review 2026-08, W3a): Kompatibilitätsprüfung →
 * healthCheck() mit Latenzmessung → Persistenz (inkl. Latenz/Code) →
 * Recorder-/Recovery-Logik → Übergangs-Events mit Hysterese. Vorher lebte
 * dieselbe Sequenz als Copy-Paste-Zwilling in HealthCheckCommand und
 * PluginController — mit garantiertem Drift (Command ohne Latenz, Controller
 * ohne Kompatibilitätsprüfung).
 *
 * Hysterese (`plugins.health_flap_threshold`, Default 2): ein Statuswechsel
 * wird erst gemeldet, wenn N Checks in Folge dasselbe neue Ergebnis liefern —
 * ok↔failing-Flapping erzeugt keine Event-/Task-Kaskaden. Der allererste
 * Status eines Plugins wird immer sofort gemeldet. `last_health_status` trägt
 * stets das ROHE letzte Ergebnis (UI), `last_announced_status` den stabilen,
 * gemeldeten Stand.
 *
 * Manuelle Checks (`$manual = true`, Admin-Button) zeichnen Fehler als Phase
 * `manual` auf — sichtbar in der Inbox, aber ohne Auto-Disable-Wirkung (E-1).
 */
class PluginHealthService {
    /** Health-Kontext-Flag für die HTTP-Factories (W3c: reduziertes Timeout, max. 1 Retry). */
    private static bool $inHealthCheck = false;

    public function __construct(private readonly PluginErrorRecorder $recorder) {}

    public static function inHealthCheck(): bool {
        return self::$inHealthCheck;
    }

    /**
     * Führt genau einen Check für (Plugin, Organisation) aus und persistiert ihn.
     *
     * @return array{health: PluginHealth, state: PluginState, auto_disabled: bool}
     */
    public function check(Plugin $plugin, ?int $organizationId, bool $manual = false): array {
        $state = PluginState::findOrInit($plugin->id(), $organizationId);
        $state->plugin_id = $plugin->id();
        $state->organization_id = $organizationId;
        $state->last_health_check_at = now();

        // Kompatibilität VOR dem Remote-Zugriff — identisch für Cron und
        // Admin-Button (behebt Drift-Befund F2.7).
        $compat = PluginCompatibility::for($plugin);
        if (! $compat->compatible) {
            $health = new PluginHealth(PluginHealth::STATUS_FAILING, $compat->message, code: 'incompatible');
            $this->persist($state, $health);
            $this->recorder->record(
                $plugin->id(),
                $manual ? PluginError::PHASE_MANUAL : PluginError::PHASE_COMPATIBILITY,
                new \RuntimeException($compat->message),
                [
                    'min_app_version' => $compat->minAppVersion,
                    'max_app_version' => $compat->maxAppVersion,
                    'app_version' => $compat->appVersion,
                ],
                $organizationId,
            );

            return $this->result($health, $state, $organizationId);
        }

        $startedAt = hrtime(true);
        try {
            self::$inHealthCheck = true;
            $health = $plugin->healthCheck();
        } catch (Throwable $e) {
            $health = new PluginHealth(PluginHealth::STATUS_FAILING, $e->getMessage(), code: 'exception');
            $exception = $e;
        } finally {
            self::$inHealthCheck = false;
        }
        $health = $health->withLatency((int) ((hrtime(true) - $startedAt) / 1_000_000));

        $this->persist($state, $health);

        if ($health->isOk()) {
            $this->recorder->markHealthy($plugin->id(), $organizationId);
        } elseif ($health->isFailing()) {
            $this->recorder->record(
                $plugin->id(),
                $manual ? PluginError::PHASE_MANUAL : PluginError::PHASE_HEALTHCHECK,
                $exception ?? new \RuntimeException($health->message !== '' ? $health->message : 'failing healthcheck'),
                [],
                $organizationId,
            );
        }
        // `degraded` zählt bewusst weder als Fehler noch als Gesundmeldung
        // (E-2): der failure_count bleibt unangetastet und in der UI sichtbar.

        return $this->result($health, $state, $organizationId);
    }

    /** Persistiert Roh-Ergebnis + Metadaten und meldet stabile Übergänge. */
    private function persist(PluginState $state, PluginHealth $health): void {
        $previousRaw = $state->last_health_status;

        $state->last_health_status = $health->status;
        $state->last_health_message = $health->message;
        $state->last_health_latency_ms = $health->latencyMs;
        $state->last_health_code = $health->code;
        if ($health->isOk()) {
            $state->last_ok_at = now();
        }

        $state->health_streak = $previousRaw === $health->status
            ? (int) $state->health_streak + 1
            : 1;

        $announced = $state->last_announced_status;
        $flapThreshold = max(1, (int) config('plugins.health_flap_threshold', 2));

        if ($announced === null) {
            // Erststatus: sofort melden — es gibt nichts, wogegen geflattert wird.
            $state->last_announced_status = $health->status;
            $this->announce($state, null, $health);
        } elseif ($health->status !== $announced && $state->health_streak >= $flapThreshold) {
            $state->last_announced_status = $health->status;
            $this->announce($state, $announced, $health);
        }

        $state->save();
    }

    private function announce(PluginState $state, ?string $from, PluginHealth $health): void {
        PluginHealthChanged::dispatch($state->plugin_id, $state->organization_id, $from, $health->status, $health->message);
        if ($health->isOk() && $from !== null) {
            PluginRecovered::dispatch($state->plugin_id, $state->organization_id, $health->message);
        }
    }

    /** @return array{health: PluginHealth, state: PluginState, auto_disabled: bool} */
    private function result(PluginHealth $health, PluginState $state, ?int $organizationId): array {
        // Auto-Disable kann soeben durch den Recorder gesetzt worden sein —
        // frisch lesen, damit die UI (updateRow, W4b) den echten Stand bekommt.
        $fresh = PluginState::findOrInit($state->plugin_id, $organizationId);

        return [
            'health' => $health,
            'state' => $fresh->exists ? $fresh : $state,
            'auto_disabled' => $fresh->isAutoDisabled(),
        ];
    }
}
