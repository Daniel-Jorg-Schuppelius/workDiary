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

use App\Enums\Licensing\ModuleStatus;
use App\Models\{AttendanceTerminal, AuditLog, BackupHeartbeat, Organization};
use App\Services\Licensing\{LicenseService, LicenseStatus, ModuleStatusResolver};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
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
    public const SECTIONS = ['version', 'license', 'modules', 'queue', 'scheduler', 'mail', 'connections', 'operations', 'storage', 'backup', 'security', 'terminals'];

    /**
     * Konnektor-Registry für die connections-Sektion (Vollaudit 2026-07, M15):
     * alle Modelle mit {@see \App\Models\Concerns\HasConnectionHealth}.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    public const CONNECTION_MODELS = [
        'email' => \App\Models\EmailConnection::class,
        'msgraph' => \App\Models\MsgraphConnection::class,
        'sharepoint' => \App\Models\SharepointConnection::class,
        'webdav' => \App\Models\WebdavConnection::class,
        'caldav' => \App\Models\CalDavConnection::class,
        'carddav' => \App\Models\CardDavConnection::class,
        'google_calendar' => \App\Models\GoogleCalendarConnection::class,
        'cti' => \App\Models\CtiConnection::class,
        'carrier' => \App\Models\CarrierConnection::class,
        'cloud_documents' => \App\Models\CloudIntake\CloudDocumentConnection::class,
        'domain_provider' => \App\Models\Domain\DomainProviderConnection::class,
        'ai_provider' => \App\Models\Ai\AiProviderConnection::class,
        'backup_target' => \App\Models\Backup\BackupTargetConnection::class,
    ];

    /** Warnschwelle: aktives Terminal ohne Kontakt seit … Stunden gilt als „stale". */
    public const TERMINAL_STALE_HOURS = 24;

    /** Warnschwelle für das Alter der SBOM (Feature 051). */
    public const SBOM_STALE_DAYS = 30;

    /**
     * Cache-Key für den Scheduler-Heartbeat. Wird von einem geplanten Job
     * (z. B. einem No-Op-Closure im Console-Kernel) gesetzt.
     */
    public const SCHEDULER_HEARTBEAT_KEY = 'scheduler.heartbeat';

    public const QUEUE_WORKER_HEARTBEAT_KEY = 'queue.worker.heartbeat';

    public function __construct(
        private readonly LicenseService $licenses,
        private readonly ModuleStatusResolver $modules,
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
                'modules' => $this->checkModules(),
                'queue' => $this->checkQueue(),
                'scheduler' => $this->checkScheduler(),
                'mail' => $this->checkMail(),
                'connections' => $this->checkConnections(),
                'operations' => $this->checkOperations(),
                'storage' => $this->checkStorage(),
                'backup' => $this->checkBackup(),
                'security' => $this->checkSecurity(),
                'terminals' => $this->checkTerminals(),
                default => DiagnosticSection::unknown($section, 'Unbekannter Diagnose-Abschnitt.'),
            };
        } catch (Throwable $e) {
            return DiagnosticSection::unknown($section, 'Check fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function checkVersion(): DiagnosticSection {
        $metrics = [
            'app_version' => (string) config('app.version', 'dev'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => (string) app()->environment(),
        ];
        $messages = [];

        // Update-Zielstand (Feature 041 P5; Vollaudit 2026-07, M15): Modus,
        // letzte Prüfung, offene/zurückgestellte Komponenten-Updates.
        try {
            $updates = app(\App\Services\Updates\UpdateCheckService::class);
            $now = CarbonImmutable::now();
            $open = \App\Models\ComponentUpdate::query()
                ->whereNull('acknowledged_at')
                ->where(fn($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', $now))
                ->count();
            $snoozed = \App\Models\ComponentUpdate::query()
                ->where('snoozed_until', '>', $now)
                ->count();
            $metrics['update_mode'] = $updates->mode();
            $metrics['update_last_check_at'] = $updates->lastCheckedAt()?->toIso8601String();
            $metrics['updates_open'] = $open;
            $metrics['updates_snoozed'] = $snoozed;
            if ($open > 0) {
                $messages[] = sprintf('%d offene Komponenten-Meldung(en) — Admin → Komponenten prüfen.', $open);
            }
        } catch (Throwable) {
            // Update-Registry nicht verfügbar (z. B. vor Migration) — Basissektion bleibt Ok.
        }

        return new DiagnosticSection(
            code: 'version',
            status: DiagnosticStatus::Ok,
            metrics: $metrics,
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Integrations-Gesundheit (Feature 041 P4, MVP-044 „Integrationen";
     * Vollaudit 2026-07, M15): je Konnektor-Typ Gesamt/gestört/deaktiviert aus
     * den {@see \App\Models\Concerns\HasConnectionHealth}-Spalten. Der
     * ExpiryScanner meldet Störungen zusätzlich als Betriebsaufgabe — hier
     * zählt der Live-Zustand.
     */
    public function checkConnections(): DiagnosticSection {
        $total = 0;
        $failing = 0;
        $disabled = 0;
        /** @var array<string, array{total:int, failing:int, disabled:int}> $detail */
        $detail = [];
        $messages = [];

        foreach (self::CONNECTION_MODELS as $key => $class) {
            try {
                /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $rows */
                $rows = $class::query()->get(['id', 'last_error', 'disabled_at']);
            } catch (Throwable) {
                continue; // Tabelle fehlt (Modul nicht migriert) — Konnektor auslassen.
            }
            if ($rows->isEmpty()) {
                continue;
            }
            $rowFailing = $rows->filter(static fn($r): bool => $r->getAttribute('last_error') !== null || $r->getAttribute('disabled_at') !== null)->count();
            $rowDisabled = $rows->filter(static fn($r): bool => $r->getAttribute('disabled_at') !== null)->count();
            $detail[$key] = ['total' => $rows->count(), 'failing' => $rowFailing, 'disabled' => $rowDisabled];
            $total += $rows->count();
            $failing += $rowFailing;
            $disabled += $rowDisabled;
            if ($rowFailing > 0) {
                $messages[] = sprintf('%s: %d von %d Verbindung(en) gestört.', $key, $rowFailing, $rows->count());
            }
        }

        if ($total === 0) {
            return new DiagnosticSection(
                code: 'connections',
                status: DiagnosticStatus::Unknown,
                metrics: ['total' => 0],
                messages: ['Keine Integrations-Verbindungen konfiguriert.'],
                checkedAt: CarbonImmutable::now(),
            );
        }

        return new DiagnosticSection(
            code: 'connections',
            status: $failing > 0 ? DiagnosticStatus::Warn : DiagnosticStatus::Ok,
            metrics: [
                'total' => $total,
                'failing' => $failing,
                'disabled' => $disabled,
                'detail' => JsonHelper::encode($detail, JSON_UNESCAPED_UNICODE),
            ],
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Betriebsaufgaben-Zusammenfassung (Feature 041 P2; Vollaudit 2026-07,
     * M15): offene/zurückgestellte Aufgaben mit Counts je Typ und Severity —
     * dieselben Zahlen erhält der Supportbericht.
     */
    public function checkOperations(): DiagnosticSection {
        $open = [];
        try {
            /** @var \Illuminate\Support\Collection<int, \App\Models\OperationsTask> $tasks */
            $tasks = \App\Models\OperationsTask::query()
                ->whereIn('status', [
                    \App\Enums\Operations\OperationsTaskStatus::Open->value,
                    \App\Enums\Operations\OperationsTaskStatus::Snoozed->value,
                    \App\Enums\Operations\OperationsTaskStatus::Delegated->value,
                ])
                ->get(['id', 'type', 'severity', 'status']);
        } catch (Throwable) {
            return new DiagnosticSection(
                code: 'operations',
                status: DiagnosticStatus::Unknown,
                metrics: [],
                messages: ['Betriebsaufgaben-Tabelle nicht verfügbar.'],
                checkedAt: CarbonImmutable::now(),
            );
        }

        $bySeverity = ['info' => 0, 'warning' => 0, 'critical' => 0];
        /** @var array<string, int> $byType */
        $byType = [];
        foreach ($tasks as $task) {
            $bySeverity[$task->severity->value]++;
            $byType[$task->type->value] = ($byType[$task->type->value] ?? 0) + 1;
        }

        $messages = [];
        $status = DiagnosticStatus::Ok;
        if ($bySeverity['critical'] > 0) {
            $status = DiagnosticStatus::Warn;
            $messages[] = sprintf('%d kritische offene Betriebsaufgabe(n) — Admin → Betrieb prüfen.', $bySeverity['critical']);
        }

        return new DiagnosticSection(
            code: 'operations',
            status: $status,
            metrics: [
                'open_total' => $tasks->count(),
                'severity_info' => $bySeverity['info'],
                'severity_warning' => $bySeverity['warning'],
                'severity_critical' => $bySeverity['critical'],
                'by_type' => JsonHelper::encode($byType, JSON_UNESCAPED_UNICODE),
            ],
            messages: $messages,
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
            LicenseStatus::BadSignature, LicenseStatus::DomainMismatch, LicenseStatus::OrgMismatch,
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

    /**
     * Modulübersicht (MVP-052 §7): pro Modul lizenziert/aktiviert/effektiv +
     * Sperrgrund. Bezugsorganisation ist die aktuell gebundene Organisation.
     */
    public function checkModules(): DiagnosticSection {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return new DiagnosticSection(
                code: 'modules',
                status: DiagnosticStatus::Unknown,
                metrics: [],
                messages: ['Keine Organisation im Kontext gebunden.'],
                checkedAt: CarbonImmutable::now(),
            );
        }

        $rows = $this->modules->forOrganization($org);
        $licensed = 0;
        $active = 0;
        $disabled = 0;
        $blocked = 0;
        /** @var array<string, array{licensed:bool, active:bool, blockReason:?string}> $detail */
        $detail = [];
        foreach ($rows as $row) {
            /** @var ModuleStatus $st */
            $st = $row['status'];
            if ($st->isLicensed()) {
                $licensed++;
            }
            if ($st === ModuleStatus::Active) {
                $active++;
            }
            if ($st === ModuleStatus::InactiveByCustomer) {
                $disabled++;
            }
            if ($st === ModuleStatus::Blocked) {
                $blocked++;
            }
            $detail[$row['code']] = [
                'licensed' => $st->isLicensed(),
                'active' => $st->isAvailable(),
                'blockReason' => $st === ModuleStatus::Active ? null : $st->value,
            ];
        }

        return new DiagnosticSection(
            code: 'modules',
            status: DiagnosticStatus::Ok,
            metrics: [
                'licensed' => $licensed,
                'active' => $active,
                'disabled_by_customer' => $disabled,
                'blocked' => $blocked,
                'detail' => JsonHelper::encode($detail, JSON_UNESCAPED_UNICODE),
            ],
            messages: [],
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

        // Registry-Job-Zustand (Feature 067, MVP-177): pausierte,
        // fehlschlagende und überfällige Jobs sichtbar machen.
        $jobsTotal = count(app(\App\Scheduling\JobRegistry::class)->all());
        $jobsPaused = count(array_filter(
            \App\Models\ScheduledJobOverride::systemMap(),
            static fn(array $override): bool => $override['enabled'] === false,
        ));
        $jobsFailing = 0;
        $jobsOverdue = 0;
        try {
            $jobsFailing = \App\Models\ScheduledJobState::query()->where('consecutive_failures', '>', 0)->count();
            $jobsOverdue = \App\Models\ScheduledJobState::query()->whereNotNull('overdue_notified_at')->count();
        } catch (\Throwable) {
            // Tabelle fehlt (vor Migration) — Zahlen bleiben 0.
        }

        if ($jobsFailing > 0) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = sprintf('%d Job(s) mit Fehlern in Folge.', $jobsFailing);
        }
        if ($jobsOverdue > 0) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = sprintf('%d überfällige(r) Job(s) — Details auf der Scheduler-Seite.', $jobsOverdue);
        }

        return new DiagnosticSection(
            code: 'scheduler',
            status: $status,
            metrics: [
                'last_run_at' => $heartbeatAt?->toIso8601String(),
                'jobs_total' => $jobsTotal,
                'jobs_paused' => $jobsPaused,
                'jobs_failing' => $jobsFailing,
                'jobs_overdue' => $jobsOverdue,
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

    /**
     * Sicherheits-/Release-Gate-Posture (Feature 051): schnelle Config-Härtung
     * und SBOM-Stand. Bewusst ohne Netzwerk/Composer-Aufruf — der vollständige
     * Gate-Lauf bleibt CI/CLI (`composer security:gate`).
     */
    public function checkSecurity(): DiagnosticSection {
        $status = DiagnosticStatus::Ok;
        $messages = [];

        $isProduction = app()->environment('production');
        $debug = (bool) config('app.debug');
        $sessionSecure = (bool) config('session.secure');
        $appUrl = (string) config('app.url', '');
        $https = str_starts_with($appUrl, 'https://');

        if ($debug) {
            $status = DiagnosticStatus::worst($status, $isProduction ? DiagnosticStatus::Critical : DiagnosticStatus::Warn);
            $messages[] = $isProduction
                ? 'APP_DEBUG ist in der Produktion aktiv — Informationsabfluss-Risiko.'
                : 'APP_DEBUG ist aktiv (für Produktion deaktivieren).';
        }
        if ($isProduction && ! $sessionSecure) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = 'Session-Cookies sind nicht auf "secure" gesetzt (SESSION_SECURE_COOKIE).';
        }
        if ($isProduction && ! $https) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = 'APP_URL ist nicht https — produktiv TLS erzwingen.';
        }

        // SBOM-Artefakt (Feature 051, MVP-098): Vorhandensein, Umfang, Alter.
        $sbomPath = storage_path('app/sbom.cdx.json');
        $sbomComponents = null;
        $sbomGeneratedAt = null;
        if (File::exists($sbomPath)) {
            try {
                /** @var array{components?: array<int, mixed>}|null $sbom */
                $sbom = JsonHelper::decode(File::get($sbomPath));
                $sbomComponents = is_array($sbom) ? count($sbom['components'] ?? []) : null;
            } catch (Throwable) {
                $sbomComponents = null;
            }
            $sbomGeneratedAt = CarbonImmutable::createFromTimestamp(File::lastModified($sbomPath));
            if ($sbomGeneratedAt->diffInDays(CarbonImmutable::now(), true) > self::SBOM_STALE_DAYS) {
                $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
                $messages[] = sprintf('SBOM älter als %d Tage — neu erzeugen (composer sbom).', self::SBOM_STALE_DAYS);
            }
        } else {
            $messages[] = 'Keine SBOM erzeugt (composer sbom / Security-Gate).';
        }

        // OSV-Sicherheitslage (Rang 70): DB-Stand, kein Netzwerkaufruf —
        // gepflegt durch `security:advisories-pull` (Scheduler/Sicherheitsseite).
        $openAdvisories = \App\Models\SecurityAdvisory::query()->whereNull('resolved_at')->count();
        $openHighAdvisories = \App\Models\SecurityAdvisory::openHighOrCritical();
        if ($openHighAdvisories > 0) {
            $status = DiagnosticStatus::worst($status, DiagnosticStatus::Warn);
            $messages[] = sprintf(
                '%d offene high/critical-Sicherheitshinweise (OSV) — Admin → Sicherheit prüfen.',
                $openHighAdvisories,
            );
        }

        return new DiagnosticSection(
            code: 'security',
            status: $status,
            metrics: [
                'environment' => (string) app()->environment(),
                'app_debug' => $debug,
                'session_secure_cookie' => $sessionSecure,
                'https_app_url' => $https,
                'sbom_components' => $sbomComponents,
                'sbom_generated_at' => $sbomGeneratedAt?->toIso8601String(),
                'advisories_open' => $openAdvisories,
                'advisories_high_or_critical' => $openHighAdvisories,
            ],
            messages: $messages,
            checkedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Gesundheitsstatus der Stempelterminals der aktuellen Organisation
     * (Feature 061): warnt, wenn ein aktives Terminal seit über
     * {@see TERMINAL_STALE_HOURS} Stunden keinen Kontakt mehr hatte. Ohne
     * konfigurierte Terminals ist die Sektion rein informativ (Unknown).
     */
    public function checkTerminals(): DiagnosticSection {
        /** @var \Illuminate\Support\Collection<int, AttendanceTerminal> $terminals */
        $terminals = AttendanceTerminal::query()->get(['id', 'name', 'active', 'last_seen_at']);
        $total = $terminals->count();

        if ($total === 0) {
            return new DiagnosticSection(
                code: 'terminals',
                status: DiagnosticStatus::Unknown,
                metrics: ['total' => 0],
                messages: ['Keine Stempelterminals konfiguriert.'],
                checkedAt: CarbonImmutable::now(),
            );
        }

        $threshold = CarbonImmutable::now()->subHours(self::TERMINAL_STALE_HOURS);
        $active = $terminals->where('active', true);
        $stale = $active->filter(
            static fn (AttendanceTerminal $terminal): bool => $terminal->last_seen_at === null || $terminal->last_seen_at->lt($threshold),
        );

        $messages = [];
        $status = DiagnosticStatus::Ok;
        if ($stale->isNotEmpty()) {
            $status = DiagnosticStatus::Warn;
            $messages[] = sprintf('%d aktive(s) Terminal(s) seit über %d h ohne Kontakt.', $stale->count(), self::TERMINAL_STALE_HOURS);
        }

        return new DiagnosticSection(
            code: 'terminals',
            status: $status,
            metrics: [
                'total' => $total,
                'active' => $active->count(),
                'stale' => $stale->count(),
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
