<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticsServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Diagnostics;

use App\Models\{AuditLog, BackupHeartbeat};
use App\Services\Diagnostics\{DiagnosticStatus, DiagnosticsService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DiagnosticsServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_version_section_reports_ok_and_includes_runtime_metrics(): void {
        $service = app(DiagnosticsService::class);
        $section = $service->checkVersion();

        $this->assertSame('version', $section->code);
        $this->assertSame(DiagnosticStatus::Ok, $section->status);
        $this->assertArrayHasKey('php_version', $section->metrics);
        $this->assertArrayHasKey('laravel_version', $section->metrics);
        $this->assertSame(PHP_VERSION, $section->metrics['php_version']);
    }

    public function test_queue_section_reports_critical_when_worker_heartbeat_stale(): void {
        Cache::put(DiagnosticsService::QUEUE_WORKER_HEARTBEAT_KEY, CarbonImmutable::now()->subMinutes(20)->toIso8601String());

        $service = app(DiagnosticsService::class);
        $section = $service->checkQueue();

        $this->assertSame(DiagnosticStatus::Critical, $section->status);
        $this->assertNotEmpty($section->messages);
    }

    public function test_queue_section_ok_when_worker_heartbeat_fresh(): void {
        Cache::put(DiagnosticsService::QUEUE_WORKER_HEARTBEAT_KEY, CarbonImmutable::now()->subMinutes(1)->toIso8601String());

        $service = app(DiagnosticsService::class);
        $section = $service->checkQueue();

        $this->assertSame(DiagnosticStatus::Ok, $section->status);
    }

    public function test_scheduler_section_critical_when_heartbeat_stale(): void {
        Cache::put(DiagnosticsService::SCHEDULER_HEARTBEAT_KEY, CarbonImmutable::now()->subMinutes(10)->toIso8601String());

        $service = app(DiagnosticsService::class);
        $section = $service->checkScheduler();

        $this->assertSame(DiagnosticStatus::Critical, $section->status);
    }

    public function test_scheduler_section_unknown_when_no_heartbeat(): void {
        Cache::forget(DiagnosticsService::SCHEDULER_HEARTBEAT_KEY);

        $service = app(DiagnosticsService::class);
        $section = $service->checkScheduler();

        $this->assertSame(DiagnosticStatus::Unknown, $section->status);
    }

    public function test_mail_section_warns_for_array_driver_in_tests(): void {
        config(['mail.default' => 'array', 'mail.from.address' => 'noreply@example.test']);

        $service = app(DiagnosticsService::class);
        $section = $service->checkMail();

        $this->assertSame(DiagnosticStatus::Warn, $section->status);
        $this->assertSame('array', $section->metrics['driver']);
    }

    public function test_collect_returns_all_sections_with_overall_worst_status(): void {
        Cache::put(DiagnosticsService::SCHEDULER_HEARTBEAT_KEY, CarbonImmutable::now()->subMinutes(10)->toIso8601String());

        $service = app(DiagnosticsService::class);
        $report = $service->collect();

        $codes = array_map(static fn($s) => $s->code, $report->sections);
        $this->assertSame(['version', 'license', 'modules', 'queue', 'scheduler', 'mail', 'connections', 'operations', 'storage', 'backup', 'security', 'terminals'], $codes);
        $this->assertSame(DiagnosticStatus::Critical, $report->overallStatus());
    }

    /** Vollaudit 2026-07 (M15): Integrations-Sektion meldet gestörte Verbindungen. */
    public function test_connections_section_warns_on_failing_connection(): void {
        $section = app(DiagnosticsService::class)->checkConnections();
        $this->assertSame(DiagnosticStatus::Unknown, $section->status); // keine Konnektoren konfiguriert

        $connection = \App\Models\EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechnungspostfach',
            'host' => 'imap.example.test',
            'username' => 'invoices',
            'password' => 'secret',
        ]);
        $connection->recordConnectionFailure('IMAP-Login fehlgeschlagen');

        $section = app(DiagnosticsService::class)->checkConnections();
        $this->assertSame(DiagnosticStatus::Warn, $section->status);
        $this->assertSame(1, $section->metrics['failing']);
    }

    /** Vollaudit 2026-07 (M15): Betriebsaufgaben-Zusammenfassung je Typ/Severity. */
    public function test_operations_section_counts_open_tasks_by_severity(): void {
        $section = app(DiagnosticsService::class)->checkOperations();
        $this->assertSame(DiagnosticStatus::Ok, $section->status);
        $this->assertSame(0, $section->metrics['open_total']);

        \App\Models\OperationsTask::query()->create([
            'organization_id' => $this->organization->id,
            'type' => \App\Enums\Operations\OperationsTaskType::cases()[0]->value,
            'severity' => \App\Enums\Operations\OperationsTaskSeverity::Critical->value,
            'status' => \App\Enums\Operations\OperationsTaskStatus::Open->value,
            'dedupe_key' => 'diag-test-1',
            'title_key' => 'operations.task.test',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $section = app(DiagnosticsService::class)->checkOperations();
        $this->assertSame(DiagnosticStatus::Warn, $section->status);
        $this->assertSame(1, $section->metrics['severity_critical']);
    }

    public function test_terminals_section_warns_on_stale_active_terminal(): void {
        $org = \App\Models\Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);

        // Aktives Terminal ohne Kontakt seit 3 Tagen → stale.
        \App\Models\AttendanceTerminal::query()->create([
            'organization_id' => $org->id,
            'name' => 'Eingang',
            'token_hash' => hash('sha256', 'stale'),
            'active' => true,
            'last_seen_at' => CarbonImmutable::now()->subDays(3),
        ]);
        // Aktives Terminal mit frischem Kontakt → ok.
        \App\Models\AttendanceTerminal::query()->create([
            'organization_id' => $org->id,
            'name' => 'Werkstatt',
            'token_hash' => hash('sha256', 'fresh'),
            'active' => true,
            'last_seen_at' => CarbonImmutable::now()->subMinutes(5),
        ]);

        $section = app(DiagnosticsService::class)->checkTerminals();

        $this->assertSame('terminals', $section->code);
        $this->assertSame(DiagnosticStatus::Warn, $section->status);
        $this->assertSame(2, $section->metrics['total']);
        $this->assertSame(1, $section->metrics['stale']);
    }

    public function test_terminals_section_is_informational_without_terminals(): void {
        $section = app(DiagnosticsService::class)->checkTerminals();

        $this->assertSame(DiagnosticStatus::Unknown, $section->status);
        $this->assertSame(0, $section->metrics['total']);
    }

    public function test_security_section_flags_debug_and_reports_sbom_metrics(): void {
        config(['app.debug' => true]);

        $service = app(DiagnosticsService::class);
        $section = $service->checkSecurity();

        $this->assertSame('security', $section->code);
        // APP_DEBUG aktiv → mindestens Warnung (in Produktion kritisch).
        $this->assertContains($section->status, [DiagnosticStatus::Warn, DiagnosticStatus::Critical]);
        $this->assertTrue($section->metrics['app_debug']);
        $this->assertArrayHasKey('sbom_components', $section->metrics);
        $this->assertNotEmpty($section->messages);
    }

    public function test_security_section_ok_when_hardened_without_warnings(): void {
        config(['app.debug' => false]);

        $service = app(DiagnosticsService::class);
        $section = $service->checkSecurity();

        // Nicht-Produktion, Debug aus → keine kritischen/Warn-Härtungsbefunde.
        $this->assertNotSame(DiagnosticStatus::Critical, $section->status);
        $this->assertFalse($section->metrics['app_debug']);
    }

    public function test_backup_section_critical_when_no_heartbeat_and_no_audit(): void {
        BackupHeartbeat::query()->delete();
        AuditLog::query()->where('event', 'like', 'backup.%')->delete();

        $service = app(DiagnosticsService::class);
        $section = $service->checkBackup();

        $this->assertSame(DiagnosticStatus::Critical, $section->status);
        $this->assertNull($section->metrics['last_backup_at']);
    }

    public function test_backup_section_ok_when_fresh_heartbeat_exists(): void {
        BackupHeartbeat::query()->delete();
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(2),
            'size_bytes' => 12345,
            'manifest_hash' => str_repeat('a', 64),
            'source' => 'backup-host.example.org',
            'ip' => '127.0.0.1',
        ]);

        $service = app(DiagnosticsService::class);
        $section = $service->checkBackup();

        $this->assertSame(DiagnosticStatus::Ok, $section->status);
        $this->assertSame(12345, $section->metrics['size_bytes']);
        $this->assertSame(str_repeat('a', 64), $section->metrics['manifest_hash']);
        $this->assertSame('backup-host.example.org', $section->metrics['source']);
    }

    public function test_backup_section_warn_when_heartbeat_older_than_warn_threshold(): void {
        BackupHeartbeat::query()->delete();
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(30),
        ]);

        $service = app(DiagnosticsService::class);
        $section = $service->checkBackup();

        $this->assertSame(DiagnosticStatus::Warn, $section->status);
    }

    public function test_backup_section_critical_when_heartbeat_older_than_critical_threshold(): void {
        BackupHeartbeat::query()->delete();
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(80),
        ]);

        $service = app(DiagnosticsService::class);
        $section = $service->checkBackup();

        $this->assertSame(DiagnosticStatus::Critical, $section->status);
    }

    public function test_backup_section_falls_back_to_audit_log_when_no_heartbeat(): void {
        BackupHeartbeat::query()->delete();
        AuditLog::query()->where('event', 'like', 'backup.%')->delete();

        $row = AuditLog::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => null,
            'event' => 'backup.completed',
            'auditable_type' => \App\Models\Organization::class,
            'auditable_id' => $this->organization->id,
            'changes' => [],
        ]);
        $row->forceFill(['created_at' => CarbonImmutable::now()->subHours(2)])->saveQuietly();

        $service = app(DiagnosticsService::class);
        $section = $service->checkBackup();

        $this->assertSame(DiagnosticStatus::Ok, $section->status);
    }
}
