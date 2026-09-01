<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerRegistrar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Services\Diagnostics\DiagnosticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\{Cache, Log};

/**
 * Registriert alle Registry-Jobs am Laravel-Scheduler (Feature 067,
 * MVP-175/180). Ersetzt die früheren Einzeleinträge in
 * routes/console.php — Standardinstallationen behalten exakt ihr
 * bisheriges Verhalten (Regressionstest SchedulerRegistrationTest).
 *
 * Ausfallsicherheit: läuft bei jedem schedule:run-Tick. Fehler beim
 * Auflösen von Settings/Overrides dürfen den Scheduler nie stoppen —
 * dann gelten die config-Defaults.
 */
class SchedulerRegistrar {
    public const HEARTBEAT_NAME = 'scheduler.heartbeat';

    /** Selbstüberwachung — wird bewusst als letzter Job eingehängt. */
    public const WATCHDOG_KEY = 'scheduler.watchdog';

    public function __construct(private readonly JobRegistry $registry) {}

    public function register(Schedule $schedule): void {
        $overrides = \App\Models\ScheduledJobOverride::systemMap();
        $watchdog = null;

        foreach ($this->registry->all() as $definition) {
            $override = $overrides[$definition->key] ?? null;
            if ($override !== null && $override['enabled'] === false) {
                continue; // pausiert (MVP-176) — Sichtbarkeit über die Adminseite
            }

            // Der Wächter urteilt über den Erfolg der anderen Jobs. Stünde er
            // mitten im Stapel, bewertete er in einem verspäteten Tick alles,
            // was hinter ihm noch aussteht, als überfällig — die Jobs liefen
            // Sekunden später und der Befund war ein Fehlalarm.
            if ($definition->key === self::WATCHDOG_KEY) {
                $watchdog = [$definition, $override];

                continue;
            }

            $this->registerJob($schedule, $definition, $override);
        }

        if ($watchdog !== null) {
            $this->registerJob($schedule, $watchdog[0], $watchdog[1]);
        }

        // Heartbeat-Writer (schließt die Diagnose-Lücke: die Diagnose-
        // Seite liest diesen Key, bisher schrieb ihn niemand).
        $schedule->call(static function (): void {
            Cache::put(
                DiagnosticsService::SCHEDULER_HEARTBEAT_KEY,
                CarbonImmutable::now()->toIso8601String(),
            );
        })->everyMinute()->name(self::HEARTBEAT_NAME);
    }

    /** @param array<string, mixed>|null $override */
    private function registerJob(Schedule $schedule, JobDefinition $definition, ?array $override): void {
        $event = $schedule->command($definition->command);
        $event->cron($this->resolveCadence($definition, $override['cadence'] ?? null)->cronExpression());

        // Wartungsfenster-Kopplung (MVP-055): Jobs mit
        // runs_in_maintenance=false pausieren im aktiven System-Fenster.
        if (! $definition->runsInMaintenance) {
            $event->skip(static fn(): bool => \App\Models\MaintenanceWindow::systemActiveNow());
        }

        if ($definition->withoutOverlapping) {
            $event->withoutOverlapping($this->lockMinutes($definition));
        }
        if ($definition->onOneServer) {
            $event->onOneServer();
        }
        if ($definition->runInBackground) {
            $event->runInBackground();
        }
    }

    /**
     * Lebensdauer der Überlappungssperre. Laravels Default sind 1440 Minuten:
     * ein abgebrochener Lauf (OOM, Neustart, SIGKILL) gibt die Sperre nicht
     * frei und legt den Job dann einen ganzen Tag still — er läuft genau
     * einmal täglich und meldet sich jeden Morgen als überfällig. Sechsfache
     * Soll-Laufzeit lässt echten Überläufen Luft und holt den Job trotzdem
     * binnen Stunden zurück.
     */
    private function lockMinutes(JobDefinition $definition): int {
        return max(30, $definition->expectedRuntimeMinutes * 6);
    }

    /** Effektive Kadenz inkl. System-Override (für Adminseite/Watchdog). */
    public function resolvedCadence(JobDefinition $definition): Cadence {
        $overrides = \App\Models\ScheduledJobOverride::systemMap();

        return $this->resolveCadence($definition, $overrides[$definition->key]['cadence'] ?? null);
    }

    /** @param array<string, mixed>|null $overrideCadence */
    private function resolveCadence(JobDefinition $definition, ?array $overrideCadence): Cadence {
        try {
            if ($overrideCadence !== null) {
                $cadence = Cadence::fromArray($overrideCadence);
                if ($definition->allowsCadence($cadence->type)) {
                    return $cadence;
                }
                Log::warning('scheduler.override_cadence_not_allowed', [
                    'job' => $definition->key,
                    'type' => $cadence->type->value,
                ]);
            }

            return $this->registry->effectiveCadence($definition);
        } catch (\Throwable $e) {
            Log::warning('scheduler.cadence_resolution_failed', [
                'job' => $definition->key,
                'message' => $e->getMessage(),
            ]);

            return $definition->defaultCadence;
        }
    }
}
