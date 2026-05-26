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
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DiagnosticsServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
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
        $this->assertSame(['version', 'license', 'queue', 'scheduler', 'mail', 'storage', 'backup'], $codes);
        $this->assertSame(DiagnosticStatus::Critical, $report->overallStatus());
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
