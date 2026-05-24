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

use App\Models\AuditLog;
use App\Services\Diagnostics\DiagnosticsService;
use Carbon\CarbonImmutable;
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
        'APP_KEY', 'APP_DEBUG_KEY', 'DB_PASSWORD', 'DB_USERNAME', 'DB_HOST', 'DB_URL',
        'LEGACY_DB_PASSWORD', 'LEGACY_DB_USERNAME', 'LEGACY_DB_HOST', 'LEGACY_DB_URL',
        'MAIL_PASSWORD', 'MAIL_USERNAME', 'MAIL_HOST', 'MAIL_FROM_ADDRESS',
        'REDIS_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'AWS_ACCESS_KEY_ID', 'AWS_BUCKET',
        'LICENSE_KEY', 'LICENSE_PUBLIC_KEY',
    ];

    public function __construct(
        private readonly DiagnosticsService $diagnostics,
        private readonly SupportReportLogFilter $logFilter,
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
            'schema_version' => 1,
            'installation' => $this->installation(),
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

            return DB::table('migrations')
                ->orderBy('batch')
                ->orderBy('name')
                ->get(['name', 'batch'])
                ->map(static fn($row): array => ['name' => (string) $row->name, 'batch' => (int) $row->batch])
                ->all();
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
                $out[$name] = $this->collectKeys($values);
                sort($out[$name]);
            } catch (Throwable) {
                $out[$name] = [];
            }
        }
        ksort($out);

        return $out;
    }

    /** @param array<string, mixed> $values @return list<string> */
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
        if (! File::isFile($envFile)) {
            return [];
        }

        $keys = [];
        foreach (explode("\n", (string) File::get($envFile)) as $line) {
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
            $name = is_array($table) ? ($table['name'] ?? null) : (is_object($table) ? ($table->name ?? null) : (string) $table);
            if (! is_string($name) || $name === '') {
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

            return $rows->map(function ($row): array {
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
            })->all();
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
        $rows = AuditLog::query()
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
        if (! File::isFile($path)) {
            return null;
        }
        try {
            return hash_file('sha256', $path) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
