<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diagnostics;

use App\Models\{AuditLog, BackupHeartbeat};
use App\Services\Licensing\{LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, DB, File};
use Throwable;

/**
 * Sammelt Live-Werte für die Diagnose-Seite (MVP-044).
 *
 * Jede Sektion ist gekapselt; Fehler in einer Sektion machen die anderen
 * nicht kaputt. Pro Sektion wird `DiagnosticStatus::Unknown` mit
 * Fehlermeldung zurückgegeben, wenn ein Check unerwartet wirft.
 */
class DiagnosticsService {
    public const SECTIONS = ['version', 'license', 'queue', 'scheduler', 'mail', 'storage', 'backup'];

    /**
     * Cache-Key für den Scheduler-Heartbeat. Wird von einem geplanten Job
     * (z. B. einem No-Op-Closure im Console-Kernel) gesetzt.
     */
    public const SCHEDULER_HEARTBEAT_KEY = 'scheduler.heartbeat';

    public const QUEUE_WORKER_HEARTBEAT_KEY = 'queue.worker.heartbeat';

    public function __construct(
        private readonly LicenseService $licenses,
    ) {}

    public function collect(): DiagnosticsReport {
        $sections = [];
        foreach (self::SECTIONS as $code) {
            $sections[] = $this->runSafe($code);
        }

        return new DiagnosticsReport(sections: $sections, generatedAt: CarbonImmutable::now());
    }

    public function runSafe(string $section): DiagnosticSection {
        try {
            return match ($section) {
                'version' => $this->checkVersion(),
                'license' => $this->checkLicense(),
                'queue' => $this->checkQueue(),
                'scheduler' => $this->checkScheduler(),
                'mail' => $this->checkMail(),
                'storage' => $this->checkStorage(),
                'backup' => $this->checkBackup(),
                default => DiagnosticSection::unknown($section, 'Unbekannter Diagnose-Abschnitt.'),
            };
        } catch (Throwable $e) {
            return DiagnosticSection::unknown($section, 'Check fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function checkVersion(): DiagnosticSection {
        return new DiagnosticSection(
            code: 'version',
            status: DiagnosticStatus::Ok,
            metrics: [
                'app_version' => (string) config('app.version', 'dev'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => (string) app()->environment(),
            ],
            messages: [],
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function checkLicense(): DiagnosticSection {
        if (! $this->licenses->isEnforced()) {
            return new DiagnosticSection(
                code: 'license',
                status: DiagnosticStatus::Ok,
                metrics: ['enforced' => false],
                messages: ['Lizenzprüfung ist deaktiviert (Dev/Test-Umgebung).'],
                checkedAt: CarbonImmutable::now(),
            );
        }

        $result = $this->licenses->current();
        $status = match ($result->status) {
            LicenseStatus::Valid => DiagnosticStatus::Ok,
            LicenseStatus::GracePeriod => DiagnosticStatus::Warn,
            LicenseStatus::Missing, LicenseStatus::Expired, LicenseStatus::Malformed,
            LicenseStatus::BadSignature, LicenseStatus::DomainMismatch,
            LicenseStatus::PublicKeyMissing, LicenseStatus::Tampered => DiagnosticStatus::Critical,
        };

        $metrics = [
            'license_status' => $result->status->value,
            'licensee' => $result->payload?->licensee,
            'expires_at' => $result->payload?->expiresAt?->toIso8601String(),
            'max_users' => $result->payload?->maxUsers,
        ];

        return new DiagnosticSection(
            code: 'license',
            status: $status,
            metrics: $metrics,
            messages: array_filter([$result->message ?? null]),
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function checkQueue(): DiagnosticSection {
        $pending = 0;
        $failed = 0;
        $lastFailedAt = null;
        $hasJobsTable = false;
        $hasFailedTable = false;

        try {
            $hasJobsTable = DB::getSchemaBuilder()->hasTable('jobs');
            if ($hasJobsTable) {
                $pending = (int) DB::table('jobs')->count();
            }
        } catch (Throwable) {
            // Schema-Sniffing-Fehler nicht hart schlagen — als Unknown markieren.
        }

        try {
            $hasFailedTable = DB::getSchemaBuilder()->hasTable('failed_jobs');
            if ($hasFailedTable) {
                $failed = (int) DB::table('failed_jobs')->count();
                /** @var object{failed_at: string|null}|null $row */
                $row = DB::table('failed_jobs')->orderByDesc('failed_at')->first(['failed_at']);
                $lastFailedAt = $row?->failed_at;
            }
        } catch (Throwable) {
        }

        $workerHeartbeat = Cache::get(self::QUEUE_WORKER_HEARTBEAT_KEY);
        $workerHeartbeatAt = $this->parseTimestamp($workerHeartbeat);

        $status = DiagnosticStatus::Ok;
        $messages = [];

        if ($pending > 200) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = sprintf('Queue-Rückstau: %d wartende Jobs.', $pending);
        }
        if ($failed > 0) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = sprintf('%d fehlgeschlagene Jobs.', $failed);
        }
        if ($workerHeartbeatAt !== null && $workerHeartbeatAt->diffInMinutes(CarbonImmutable::now(), true) > 5) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Critical);
            $messages[] = 'Queue-Worker-Heartbeat älter als 5 Minuten.';
        }
        if ($workerHeartbeatAt === null) {
            $messages[] = 'Kein Queue-Worker-Heartbeat vorhanden (Cache-Key ' . self::QUEUE_WORKER_HEARTBEAT_KEY . ').';
        }

        return new DiagnosticSection(
            code: 'queue',
            status: $status,
            metrics: [
                'pending' => $pending,
                'failed' => $failed,
                'last_failed_at' => $lastFailedAt,
                'worker_heartbeat_at' => $workerHeartbeatAt?->toIso8601String(),
                'has_jobs_table' => $hasJobsTable,
                'has_failed_jobs_table' => $hasFailedTable,
            ],
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function checkScheduler(): DiagnosticSection {
        $heartbeatAt = $this->parseTimestamp(Cache::get(self::SCHEDULER_HEARTBEAT_KEY));
        $status = DiagnosticStatus::Ok;
        $messages = [];

        if ($heartbeatAt === null) {
            $status = DiagnosticStatus::Unknown;
            $messages[] = 'Kein Scheduler-Heartbeat vorhanden (Cache-Key ' . self::SCHEDULER_HEARTBEAT_KEY . ').';
        } elseif ($heartbeatAt->diffInMinutes(CarbonImmutable::now(), true) > 5) {
            $status = DiagnosticStatus::Critical;
            $messages[] = sprintf(
                'Scheduler-Heartbeat älter als 5 Minuten (zuletzt %s).',
                $heartbeatAt->diffForHumans()
            );
        }

        return new DiagnosticSection(
            code: 'scheduler',
            status: $status,
            metrics: [
                'last_run_at' => $heartbeatAt?->toIso8601String(),
            ],
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function checkMail(): DiagnosticSection {
        $driver = (string) config('mail.default', 'log');
        $fromAddress = (string) config('mail.from.address', '');
        $messages = [];
        $status = DiagnosticStatus::Ok;

        if ($driver === 'array' || $driver === 'log') {
            $status = DiagnosticStatus::Warn;
            $messages[] = sprintf('Mail-Driver "%s" ist für Produktion ungeeignet.', $driver);
        }
        if ($fromAddress === '') {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = 'Kein From-Address konfiguriert (MAIL_FROM_ADDRESS).';
        }

        return new DiagnosticSection(
            code: 'mail',
            status: $status,
            metrics: [
                'driver' => $driver,
                'from' => $fromAddress,
            ],
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function checkStorage(): DiagnosticSection {
        $messages = [];
        $status = DiagnosticStatus::Ok;
        $metrics = [];

        $disks = ['local', 'public'];
        foreach ($disks as $diskName) {
            $diskCfg = (array) config('filesystems.disks.' . $diskName, []);
            $root = (string) ($diskCfg['root'] ?? '');
            if ($root === '' || ! File::isDirectory($root)) {
                $metrics['disk.' . $diskName] = null;
                continue;
            }
            $bytes = $this->dirSize($root);
            $metrics['disk.' . $diskName . '.bytes'] = $bytes;
            $metrics['disk.' . $diskName . '.root'] = $root;
        }

        // Plattenfüllgrad falls verfügbar.
        $diskFree = @disk_free_space(base_path()) ?: null;
        $diskTotal = @disk_total_space(base_path()) ?: null;
        if ($diskFree !== null && $diskTotal !== null && $diskTotal > 0) {
            $usedRatio = 1 - ($diskFree / $diskTotal);
            $metrics['fs.used_percent'] = (int) round($usedRatio * 100);
            if ($usedRatio > 0.95) {
                $status = DiagnosticStatus::Critical;
                $messages[] = sprintf('Dateisystem >95%% belegt (%d%%).', (int) round($usedRatio * 100));
            } elseif ($usedRatio > 0.80) {
                $status = DiagnosticStatus::Warn;
                $messages[] = sprintf('Dateisystem >80%% belegt (%d%%).', (int) round($usedRatio * 100));
            }
        }

        return new DiagnosticSection(
            code: 'storage',
            status: $status,
            metrics: $metrics,
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function checkBackup(): DiagnosticSection {
        $messages = [];
        $status = DiagnosticStatus::Ok;
        $lastBackupAt = null;
        $manifestHash = null;
        $sizeBytes = null;
        $source = null;

        // 1) Bevorzugt: dedizierte Heartbeat-Tabelle (MVP-046 §5).
        try {
            if (DB::getSchemaBuilder()->hasTable((new BackupHeartbeat())->getTable())) {
                /** @var BackupHeartbeat|null $last */
                $last = BackupHeartbeat::query()->orderByDesc('occurred_at')->first();
                if ($last !== null) {
                    $lastBackupAt = $last->occurred_at;
                    $manifestHash = $last->manifest_hash;
                    $sizeBytes = $last->size_bytes;
                    $source = $last->source;
                }
            }
        } catch (Throwable) {
            // Fallback unten greift.
        }

        // 2) Fallback: Audit-Log (Backward-Compat).
        if ($lastBackupAt === null) {
            try {
                $hasAudit = DB::getSchemaBuilder()->hasTable((new AuditLog())->getTable());
                if ($hasAudit) {
                    /** @var AuditLog|null $last */
                    $last = AuditLog::query()
                        ->whereIn('event', ['backup.completed', 'backup.succeeded', 'backup.heartbeatReceived'])
                        ->orderByDesc('created_at')
                        ->first();
                    $lastBackupAt = $last?->created_at;
                }
            } catch (Throwable) {
                $lastBackupAt = null;
            }
        }

        /** @var array<string, int> $thresholds */
        $thresholds = (array) config('backup.thresholds_hours', ['warn' => 26, 'critical' => 72]);
        $warnHours = isset($thresholds['warn']) ? (int) $thresholds['warn'] : 26;
        $criticalHours = isset($thresholds['critical']) ? (int) $thresholds['critical'] : 72;

        if ($lastBackupAt === null) {
            $status = DiagnosticStatus::Critical;
            $messages[] = 'Kein erfolgreicher Backup-Heartbeat gefunden.';
        } else {
            $ageHours = CarbonImmutable::parse($lastBackupAt)->diffInHours(CarbonImmutable::now(), true);
            if ($ageHours > $criticalHours) {
                $status = DiagnosticStatus::Critical;
                $messages[] = sprintf('Letztes Backup älter als %d Stunden (%d h).', $criticalHours, (int) $ageHours);
            } elseif ($ageHours > $warnHours) {
                $status = DiagnosticStatus::Warn;
                $messages[] = sprintf('Letztes Backup älter als %d Stunden (%d h).', $warnHours, (int) $ageHours);
            }
        }

        return new DiagnosticSection(
            code: 'backup',
            status: $status,
            metrics: [
                'last_backup_at' => $lastBackupAt instanceof \DateTimeInterface ? $lastBackupAt->format(\DateTimeInterface::ATOM) : $lastBackupAt,
                'manifest_hash' => $manifestHash,
                'size_bytes' => $sizeBytes,
                'source' => $source,
            ],
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    private function parseTimestamp(mixed $value): ?CarbonImmutable {
        if ($value === null) {
            return null;
        }
        try {
            if ($value instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }
            if (is_numeric($value)) {
                return CarbonImmutable::createFromTimestamp((int) $value);
            }
            if (is_string($value) && $value !== '') {
                return CarbonImmutable::parse($value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function dirSize(string $path): int {
        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Throwable) {
            return 0;
        }

        return $size;
    }
}
