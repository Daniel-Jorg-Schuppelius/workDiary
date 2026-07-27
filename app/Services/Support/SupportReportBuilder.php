<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Support;

use App\Models\{AuditLog, PluginError};
use App\Services\Diagnostics\DiagnosticsService;
use App\Services\Release\ReleaseManifestService;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Support\Facades\{DB, File};
use Throwable;

/**
 * Erzeugt das anonymisierte Supportbericht-Bundle (MVP-045 §2). Liefert
 * eine reine Array-Struktur — Serialisierung (JSON, ZIP) macht der
 * aufrufende Code, damit das Bauen leicht testbar bleibt.
 */
class SupportReportBuilder {
    /** ENV-Schlüssel, deren Wert NIE im Bundle landen darf. Spec §2.3. */
    private const SENSITIVE_ENV_KEYS = [
        'APP_KEY',
        'APP_DEBUG_KEY',
        'DB_PASSWORD',
        'DB_USERNAME',
        'DB_HOST',
        'DB_URL',
        'LEGACY_DB_PASSWORD',
        'LEGACY_DB_USERNAME',
        'LEGACY_DB_HOST',
        'LEGACY_DB_URL',
        'MAIL_PASSWORD',
        'MAIL_USERNAME',
        'MAIL_HOST',
        'MAIL_FROM_ADDRESS',
        'REDIS_PASSWORD',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_ACCESS_KEY_ID',
        'AWS_BUCKET',
        'LICENSE_KEY',
        'LICENSE_PUBLIC_KEY',
    ];

    public function __construct(
        private readonly DiagnosticsService $diagnostics,
        private readonly SupportReportLogFilter $logFilter,
        private readonly SupportHealthSummary $health,
        private readonly ReleaseManifestService $release,
    ) {}

    /**
     * @param  array{include_samples?:bool,include_schema?:bool,log_tail?:int,failed_jobs_limit?:int}  $options
     * @return array<string, mixed>
     */
    public function build(array $options = []): array {
        $generatedAt = CarbonImmutable::now();
        $logTail = max(1, min(2000, (int) ($options['log_tail'] ?? 500)));
        $failedJobsLimit = max(1, min(1000, (int) ($options['failed_jobs_limit'] ?? 200)));

        return [
            'generated_at' => $generatedAt->toIso8601String(),
            'schema_version' => 2,
            'installation' => $this->installation(),
            'release' => $this->release(),
            'health' => $this->health->collect(),
            'plugin_errors' => $this->pluginErrorCounts(),
            'operations' => $this->operations(),
            'diagnostics' => $this->diagnostics->collect()->toArray(),
            'composer' => $this->composerHashes(),
            'npm' => $this->npmHashes(),
            'migrations' => $this->migrations(),
            'config_keys' => $this->configKeys(),
            'env_keys' => $this->envKeys(),
            'table_row_counts' => $this->tableRowCounts(),
            'failed_jobs' => $this->failedJobs($failedJobsLimit),
            'log_tail' => $this->logTail($logTail),
            'audit_event_counts' => $this->auditEventCounts(),
            'configuration' => $this->configurationSnapshot(),
            'scheduler' => $this->schedulerSnapshot(),
            'updates' => $this->updatesSnapshot(),
            'options' => [
                'include_samples' => (bool) ($options['include_samples'] ?? false),
                'include_schema' => (bool) ($options['include_schema'] ?? false),
                'log_tail' => $logTail,
                'failed_jobs_limit' => $failedJobsLimit,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function installation(): array {
        return [
            'app_version' => (string) config('app.version', 'dev'),
            'environment' => (string) app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'os' => PHP_OS_FAMILY,
            'locale' => (string) app()->getLocale(),
            'timezone' => (string) config('app.timezone', 'UTC'),
        ];
    }

    /**
     * Release-/Build-Metadaten aus dem signaturfreien Manifest-Kern
     * (Versionen, Build-Hash, aktive Module, Plugins). Whitelist-Quelle:
     * {@see ReleaseManifestService::payload()} liefert ausschließlich
     * technische Felder, KEINE Secrets/Signaturen.
     *
     * @return array<string, mixed>
     */
    private function release(): array {
        try {
            $payload = $this->release->payload();

            // Bewusst nur die rein technischen Sektionen übernehmen — keine
            // Artefakt-Checksummen-Pfade, keine Signatur.
            return [
                'application' => $payload['application'] ?? [],
                'runtime' => $payload['runtime'] ?? [],
                'modules' => $payload['modules'] ?? [],
                'plugins' => $payload['plugins'] ?? [],
            ];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Plugin-Fehler der letzten 7 Tage — NUR Plugin-ID / Phase / Anzahl.
     * KEINE Messages, KEINE Traces, KEINE Kontext-Payloads (Datensparsamkeit,
     * Feature 041 §2.3). Aggregiert über GROUP BY, damit gar keine
     * Einzeltexte aufgenommen werden.
     *
     * @return array{window_days:int, total:int, by_plugin_phase: list<array{plugin_id:string, phase:string, count:int}>}
     */
    private function pluginErrorCounts(): array {
        $windowDays = 7;
        $out = ['window_days' => $windowDays, 'total' => 0, 'by_plugin_phase' => []];

        try {
            if (! DB::getSchemaBuilder()->hasTable((new PluginError())->getTable())) {
                return $out;
            }
        } catch (Throwable) {
            return $out;
        }

        $since = CarbonImmutable::now()->subDays($windowDays);

        try {
            $rows = DB::table((new PluginError())->getTable())
                ->where('occurred_at', '>=', $since)
                ->selectRaw('plugin_id, phase, COUNT(*) as cnt')
                ->groupBy('plugin_id', 'phase')
                ->orderBy('plugin_id')
                ->orderBy('phase')
                ->get();
        } catch (Throwable) {
            return $out;
        }

        $total = 0;
        $byPluginPhase = [];
        foreach ($rows as $row) {
            $count = (int) $row->cnt;
            $total += $count;
            $byPluginPhase[] = [
                'plugin_id' => (string) $row->plugin_id,
                'phase' => (string) $row->phase,
                'count' => $count,
            ];
        }

        return ['window_days' => $windowDays, 'total' => $total, 'by_plugin_phase' => $byPluginPhase];
    }

    /**
     * Betriebs-Kennzahlen für die Diagnose: Queue-Stand und letzte
     * Backup-Heartbeats. NUR Counts/Metadaten (Größe, Zeitpunkt, Quelle) —
     * keine Inhalte. Quelle absichtlich rein technisch.
     *
     * @return array<string, mixed>
     */
    private function operations(): array {
        $out = [
            'queue' => ['available' => false, 'pending' => null, 'failed' => null],
            'backup' => ['last_heartbeat_at' => null, 'last_size_bytes' => null, 'last_source' => null, 'count_30d' => 0],
        ];

        try {
            $schema = DB::getSchemaBuilder();
            $hasJobs = $schema->hasTable('jobs');
            $hasFailed = $schema->hasTable('failed_jobs');
            if ($hasJobs || $hasFailed) {
                $out['queue'] = [
                    'available' => true,
                    'pending' => $hasJobs ? (int) DB::table('jobs')->count() : null,
                    'failed' => $hasFailed ? (int) DB::table('failed_jobs')->count() : null,
                ];
            }
        } catch (Throwable) {
            // queue bleibt unavailable
        }

        try {
            if (DB::getSchemaBuilder()->hasTable('backup_heartbeats')) {
                $latest = DB::table('backup_heartbeats')->orderByDesc('occurred_at')->first();
                $count30d = (int) DB::table('backup_heartbeats')
                    ->where('occurred_at', '>=', CarbonImmutable::now()->subDays(30))
                    ->count();
                $out['backup'] = [
                    'last_heartbeat_at' => $latest->occurred_at ?? null,
                    'last_size_bytes' => $latest->size_bytes?->getBytes(),
                    'last_source' => $latest->source ?? null,
                    'count_30d' => $count30d,
                ];
            }
        } catch (Throwable) {
            // backup bleibt leer
        }

        // Betriebsaufgaben-Zusammenfassung (Feature 041 P2; Vollaudit 2026-07,
        // M15): NUR Counts je Typ/Severity — keine Titel/Parameter.
        try {
            if (DB::getSchemaBuilder()->hasTable('operations_tasks')) {
                $openStatuses = ['open', 'snoozed', 'delegated'];
                $byType = DB::table('operations_tasks')
                    ->whereIn('status', $openStatuses)
                    ->selectRaw('type, COUNT(*) AS cnt')
                    ->groupBy('type')
                    ->pluck('cnt', 'type')
                    ->map(static fn($v): int => (int) $v)
                    ->all();
                $bySeverity = DB::table('operations_tasks')
                    ->whereIn('status', $openStatuses)
                    ->selectRaw('severity, COUNT(*) AS cnt')
                    ->groupBy('severity')
                    ->pluck('cnt', 'severity')
                    ->map(static fn($v): int => (int) $v)
                    ->all();
                $out['tasks'] = [
                    'open_total' => array_sum($byType),
                    'by_type' => $byType,
                    'by_severity' => $bySeverity,
                ];
            }
        } catch (Throwable) {
            // tasks bleibt leer
        }

        return $out;
    }

    /** @return array<string, string|null> */
    private function composerHashes(): array {
        $base = base_path();
        return [
            'composer_json_sha256' => $this->fileHash($base . '/composer.json'),
            'composer_lock_sha256' => $this->fileHash($base . '/composer.lock'),
        ];
    }

    /** @return array<string, string|null> */
    private function npmHashes(): array {
        $base = base_path();
        return [
            'package_json_sha256' => $this->fileHash($base . '/package.json'),
            'package_lock_sha256' => $this->fileHash($base . '/package-lock.json'),
        ];
    }

    /** @return list<array{name:string, batch:int}> */
    private function migrations(): array {
        try {
            if (! DB::getSchemaBuilder()->hasTable('migrations')) {
                return [];
            }

            return array_values(DB::table('migrations')
                ->orderBy('batch')
                ->orderBy('name')
                ->get(['name', 'batch'])
                ->map(static fn($row): array => ['name' => (string) $row->name, 'batch' => (int) $row->batch])
                ->all());
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, list<string>> */
    private function configKeys(): array {
        $dir = config_path();
        if (! File::isDirectory($dir)) {
            return [];
        }

        $out = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $name = $file->getBasename('.php');
            try {
                $values = (array) config($name);
                $keys = $this->collectKeys($values);
                sort($keys);
                $out[$name] = $keys;
            } catch (Throwable) {
                $out[$name] = [];
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function collectKeys(array $values, string $prefix = ''): array {
        $keys = [];
        foreach ($values as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $keys[] = $full;
            if (is_array($value)) {
                foreach ($this->collectKeys($value, $full) as $sub) {
                    $keys[] = $sub;
                }
            }
        }

        return $keys;
    }

    /** @return list<string> */
    private function envKeys(): array {
        $envFile = base_path('.env');
        if (! ToolkitFile::exists($envFile)) {
            return [];
        }

        $keys = [];
        foreach (explode("\n", ToolkitFile::read($envFile)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = substr($line, 0, $eq);
            // Sentinel: alle bekannten sensitiven Schlüssel werden gar nicht
            // mit ihrem Wert erfasst. Spec §2.3.
            if (! in_array($key, self::SENSITIVE_ENV_KEYS, true)) {
                $keys[] = $key;
            } else {
                $keys[] = $key . '=<redacted>';
            }
        }

        sort($keys);

        return $keys;
    }

    /** @return array<string, int> */
    private function tableRowCounts(): array {
        $out = [];
        try {
            $tables = DB::getSchemaBuilder()->getTables();
        } catch (Throwable) {
            return $out;
        }

        foreach ($tables as $table) {
            $name = $table['name'];
            if ($name === '') {
                continue;
            }
            try {
                $out[$name] = (int) DB::table($name)->count();
            } catch (Throwable) {
                $out[$name] = -1;
            }
        }
        ksort($out);

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function failedJobs(int $limit): array {
        try {
            if (! DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return [];
            }

            $rows = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get(['id', 'connection', 'queue', 'exception', 'failed_at']);

            return array_values($rows->map(function ($row): array {
                $exceptionText = (string) ($row->exception ?? '');
                $firstLine = strtok($exceptionText, "\n") ?: '';
                // Klasse extrahieren, KEINE Payloads aufnehmen.
                $class = '';
                if (preg_match('/^([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/', $firstLine, $m)) {
                    $class = $m[1];
                }

                return [
                    'id' => $row->id,
                    'connection' => $row->connection ?? null,
                    'queue' => $row->queue ?? null,
                    'exception_class' => $class,
                    'exception_message' => $this->logFilter->filter($firstLine),
                    'failed_at' => $row->failed_at ?? null,
                ];
            })->all());
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function logTail(int $tail): array {
        $logFile = storage_path('logs/laravel.log');
        if (! File::isFile($logFile)) {
            return [];
        }
        try {
            $content = File::get($logFile);
        } catch (Throwable) {
            return [];
        }

        $lines = preg_split("/\r?\n/", $content) ?: [];
        $slice = array_slice($lines, max(0, count($lines) - $tail));

        return $this->logFilter->filterMany($slice);
    }

    /** @return array<string, int> */
    private function auditEventCounts(): array {
        try {
            if (! DB::getSchemaBuilder()->hasTable((new AuditLog())->getTable())) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $since = CarbonImmutable::now()->subDay();
        $rows = DB::table((new AuditLog())->getTable())
            ->where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as cnt')
            ->groupBy('event')
            ->orderBy('event')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->event] = (int) $row->cnt;
        }

        return $out;
    }

    private function fileHash(string $path): ?string {
        if (! ToolkitFile::exists($path)) {
            return null;
        }
        try {
            return ToolkitFile::hash($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Redaktierter Konfigurationsstand aus der Settings-Registry
     * (Feature 067, MVP-179): je Key Wert + Herkunft; sensitive Werte
     * erscheinen ausschließlich als <redacted>.
     *
     * @return list<array<string, mixed>>
     */
    private function configurationSnapshot(): array {
        try {
            $registry = app(\App\Settings\SettingsRegistry::class);
            $snapshot = [];
            foreach (array_keys($registry->all()) as $key) {
                $effective = $registry->effective($key);
                $snapshot[] = [
                    'key' => $key,
                    'source' => $effective->source->value,
                    'value' => $effective->exportValue(),
                ];
            }

            return $snapshot;
        } catch (Throwable $e) {
            return [['key' => '_error', 'source' => 'error', 'value' => $e->getMessage()]];
        }
    }

    /**
     * Scheduler-Zustand (Feature 067, MVP-179): effektiver Plan +
     * Herkunft, Pausen und Laufzeit-Aggregat je Registry-Job — ohne
     * Secrets oder fachliche Daten.
     *
     * @return list<array<string, mixed>>
     */
    private function schedulerSnapshot(): array {
        try {
            $registry = app(\App\Scheduling\JobRegistry::class);
            $registrar = app(\App\Scheduling\SchedulerRegistrar::class);
            $overrides = \App\Models\ScheduledJobOverride::systemMap();
            $states = \App\Models\ScheduledJobState::query()->get()->keyBy('job_key');

            $snapshot = [];
            foreach ($registry->all() as $key => $definition) {
                $override = $overrides[$key] ?? null;
                $state = $states->get($key);
                $snapshot[] = [
                    'job' => $key,
                    'cron' => $registrar->resolvedCadence($definition)->cronExpression(),
                    'source' => ($override['cadence'] ?? null) !== null ? 'override' : ($definition->cadenceSettingKey !== null ? 'setting' : 'default'),
                    'enabled' => $override['enabled'] ?? true,
                    'last_status' => $state?->last_status,
                    'last_success_at' => $state?->last_success_at?->toIso8601String(),
                    'consecutive_failures' => $state !== null ? (int) $state->consecutive_failures : 0,
                ];
            }

            return $snapshot;
        } catch (Throwable $e) {
            return [['job' => '_error', 'cron' => '', 'source' => 'error', 'enabled' => false, 'last_status' => $e->getMessage(), 'last_success_at' => null, 'consecutive_failures' => 0]];
        }
    }

    /**
     * Update-Status (MVP-054/179): Modus, letzte Prüfung, offene Updates
     * inkl. Stummschaltungen — Transparenz-DoD Feature 022/041.
     *
     * @return array<string, mixed>
     */
    private function updatesSnapshot(): array {
        try {
            $updates = app(\App\Services\Updates\UpdateCheckService::class);
            $pending = \App\Models\ComponentUpdate::query()->get();

            return [
                'mode' => $updates->mode(),
                'last_checked_at' => $updates->lastCheckedAt()?->toIso8601String(),
                'pending' => $pending->map(fn(\App\Models\ComponentUpdate $u): array => [
                    'component' => $u->component_type . ':' . $u->component_key,
                    'installed' => $u->installed_version,
                    'available' => $u->available_version,
                    'classification' => $u->classification,
                    'muted' => $u->isMuted(),
                ])->values()->all(),
            ];
        } catch (Throwable $e) {
            return ['mode' => 'error', 'last_checked_at' => null, 'pending' => [], 'error' => $e->getMessage()];
        }
    }
}
