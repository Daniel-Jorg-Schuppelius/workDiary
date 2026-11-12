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
use Illuminate\Console\Scheduling\{Event, Schedule};
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
    public const string HEARTBEAT_NAME = 'scheduler.heartbeat';

    /** Selbstüberwachung — wird bewusst als letzter Job eingehängt. */
    public const string WATCHDOG_KEY = 'scheduler.watchdog';

    private ?OperatingWindow $window = null;

    private bool $windowResolved = false;

    public function __construct(private readonly JobRegistry $registry) {}

    public function register(Schedule $schedule): void {
        $overrides = \App\Models\Platform\ScheduledJobOverride::systemMap();
        $window = $this->operatingWindow();
        $watchdog = null;

        $skipInactive = (bool) config('scheduler.skip_inactive_plugins', true);

        foreach ($this->registry->all() as $definition) {
            $override = $overrides[$definition->key] ?? null;
            if ($override !== null && $override['enabled'] === false) {
                continue; // pausiert (MVP-176) — Sichtbarkeit über die Adminseite
            }
            // Plugin-gebundene Jobs ohne irgendwo aktives Plugin bekommen keinen
            // Prozess (Leistungsanalyse 2026-11-02, perf-01: ~25 Syncs je Stunde
            // für nie konfigurierte Plugins); der Wächter urteilt gleich.
            if ($skipInactive && ! PluginBinding::active($definition->plugin)) {
                continue;
            }

            // Der Wächter urteilt über den Erfolg der anderen Jobs. Stünde er
            // mitten im Stapel, bewertete er in einem verspäteten Tick alles,
            // was hinter ihm noch aussteht, als überfällig — die Jobs liefen
            // Sekunden später und der Befund war ein Fehlalarm.
            if ($definition->key === self::WATCHDOG_KEY) {
                $watchdog = [$definition, $override];

                continue;
            }

            $this->registerJob($schedule, $definition, $override, $window);
        }

        if ($watchdog !== null) {
            $this->registerJob($schedule, $watchdog[0], $watchdog[1], $window);
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
    private function registerJob(Schedule $schedule, JobDefinition $definition, ?array $override, ?OperatingWindow $window): void {
        $cadence = $this->resolveCadence($definition, $override['cadence'] ?? null);
        $timezone = is_string($override['timezone'] ?? null) ? $override['timezone'] : null;
        $quiet = OperatingWindow::fromTimes($override['quiet_until'] ?? null, $override['quiet_from'] ?? null);
        $effectiveWindow = $this->windowFor($override, $window);

        $event = $schedule->command($definition->command);
        $event->cron(($effectiveWindow?->apply($cadence) ?? $cadence)->cronExpression());
        $this->configureEvent($event, $definition, $timezone, $quiet);

        $retries = (int) ($override['retry_attempts'] ?? 0);
        if ($retries < 1) {
            return;
        }

        $key = $definition->key;
        $delay = max(1, (int) ($override['retry_delay_minutes'] ?? SchedulerOverrideService::DEFAULT_RETRY_DELAY));
        $mutex = 'scheduler-job:' . $key;
        $event->createMutexNameUsing($mutex)
            ->onFailure(static fn () => JobRetry::afterFailure($key, $retries, $delay))
            ->onSuccess(static fn () => JobRetry::clear($key));

        $retry = $schedule->command($definition->command)->everyMinute()->name($key . ':retry');
        $retry->when(static fn (): bool => JobRetry::isDue($key));
        $this->configureEvent($retry, $definition, $timezone, $quiet);
        $retry->createMutexNameUsing($mutex)
            ->before(static fn () => JobRetry::claim($key))
            ->onFailure(static fn () => JobRetry::afterRetryFailure($key, $retries, $delay))
            ->onSuccess(static fn () => JobRetry::clear($key));
    }

    /** Laufbedingungen, die für den regulären Lauf und die Wiederholung gleich gelten. */
    private function configureEvent(Event $event, JobDefinition $definition, ?string $timezone, ?OperatingWindow $quiet): void {
        if ($timezone !== null) {
            $event->timezone($timezone);
        }
        // Verschobene Uhrzeiten liegen schon im Fenster; die Sperre trifft die Takt-Kadenzen.
        if ($quiet !== null) {
            $zone = $timezone ?? (string) config('app.schedule_timezone', config('app.timezone'));
            $event->skip(static fn (): bool => ! $quiet->isOpenAt(CarbonImmutable::now($zone)));
        }

        // Wartungsfenster-Kopplung (MVP-055): Jobs mit
        // runs_in_maintenance=false pausieren im aktiven System-Fenster.
        if (! $definition->runsInMaintenance) {
            $event->skip(static fn(): bool => \App\Models\Asset\MaintenanceWindow::systemActiveNow());
        }

        if ($definition->guard !== null) {
            $event->when(fn(): bool => $this->guardAllows($definition));
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

    /** Vorprüfung (perf-02); ein Fehler in der Prüfung lässt den Job laufen, nie stillstehen. */
    private function guardAllows(JobDefinition $definition): bool {
        try {
            $guard = app((string) $definition->guard);

            return ! $guard instanceof Contracts\JobGuard || $guard->shouldRun();
        } catch (\Throwable $e) {
            Log::warning('scheduler.guard_failed', ['job' => $definition->key, 'message' => $e->getMessage()]);

            return true;
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

    /** Effektive Kadenz inkl. System-Override und Betriebsfenster (für Adminseite/Watchdog). */
    public function resolvedCadence(JobDefinition $definition): Cadence {
        $override = \App\Models\Platform\ScheduledJobOverride::systemMap()[$definition->key] ?? null;
        $cadence = $this->resolveCadence($definition, $override['cadence'] ?? null);

        return $this->windowFor($override, $this->operatingWindow())?->apply($cadence) ?? $cadence;
    }

    /**
     * Eine eigene Ruhezeit ersetzt das Betriebsfenster; eine eigene Zeitzone
     * ebenso, weil das Fenster in der Zeitzone der Installation gilt (MVP-1619).
     *
     * @param array<string, mixed>|null $override
     */
    private function windowFor(?array $override, ?OperatingWindow $installationWindow): ?OperatingWindow {
        $quiet = OperatingWindow::fromTimes($override['quiet_until'] ?? null, $override['quiet_from'] ?? null);
        if ($quiet !== null) {
            return $quiet;
        }

        return is_string($override['timezone'] ?? null) ? null : $installationWindow;
    }

    /** Eingestellte Kadenz vor der Verschiebung ins Betriebsfenster (Umplanen-Dialog). */
    public function configuredCadence(JobDefinition $definition): Cadence {
        $overrides = \App\Models\Platform\ScheduledJobOverride::systemMap();

        return $this->resolveCadence($definition, $overrides[$definition->key]['cadence'] ?? null);
    }

    /**
     * Betriebsfenster aus den Einstellungen; je Instanz einmal gelesen —
     * der Registrar ist kein Singleton, lebt also nur einen Lauf/Request.
     * Ein Lesefehler darf den Scheduler nicht stoppen: dann rund um die Uhr.
     */
    public function operatingWindow(): ?OperatingWindow {
        if ($this->windowResolved) {
            return $this->window;
        }
        $this->windowResolved = true;

        try {
            return $this->window = OperatingWindow::fromSettings();
        } catch (\Throwable $e) {
            Log::warning('scheduler.operating_window_failed', ['message' => $e->getMessage()]);

            return $this->window = null;
        }
    }

    /** @param array<string, mixed>|null $overrideCadence */
    private function resolveCadence(JobDefinition $definition, ?array $overrideCadence): Cadence {
        return $this->staggered($definition, $this->chooseCadence($definition, $overrideCadence));
    }

    /** @param array<string, mixed>|null $overrideCadence */
    private function chooseCadence(JobDefinition $definition, ?array $overrideCadence): Cadence {
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

    /** Versatz der Takt-Kadenzen aus dem Job-Key (perf-01); Überrides tragen keinen Versatz, er gilt für beide. */
    private function staggered(JobDefinition $definition, Cadence $cadence): Cadence {
        if (! $definition->stagger) {
            return $cadence;
        }
        $offset = JobOffset::minutes($definition->key, $cadence->type);

        return $offset === 0 ? $cadence : $cadence->withOffset($offset);
    }
}
