<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsAlertServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskStatus, OperationsTaskType};
use App\Models\{OperationsTask, User};
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsAlertServiceTest extends TestCase {
    use RefreshDatabase;

    private OperationsAlertService $service;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        // Admin in der (ersten) Organisation = Empfänger der Betriebs-Events.
        $this->admin = User::factory()->admin()->create();
        $this->service = app(OperationsAlertService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function signal(array $overrides = []): OperationsSignal {
        return new OperationsSignal(
            type: $overrides['type'] ?? OperationsTaskType::BackupOverdue,
            dedupeKey: $overrides['dedupeKey'] ?? 'backup_overdue',
            severity: $overrides['severity'] ?? OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.backup_overdue',
            params: ['hours' => 30, 'threshold' => 26],
            organizationId: array_key_exists('organizationId', $overrides) ? $overrides['organizationId'] : null,
        );
    }

    public function test_report_creates_system_task_and_notifies_admin(): void {
        $task = $this->service->report($this->signal());

        $this->assertNotNull($task);
        $this->assertTrue($task->is_system);
        $this->assertSame((int) $this->admin->organization_id, (int) $task->organization_id);
        $this->assertSame(OperationsTaskStatus::Open, $task->status);

        // In-App-Notification beim Admin angekommen (database channel).
        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_repeated_report_is_idempotent_and_does_not_renotify(): void {
        $this->service->report($this->signal());
        $this->service->report($this->signal());
        $this->service->report($this->signal());

        $this->assertDatabaseCount('operations_tasks', 1);
        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_escalation_raises_severity_and_renotifies(): void {
        $this->service->report($this->signal());
        $this->service->report($this->signal(['severity' => OperationsTaskSeverity::Critical]));

        $task = OperationsTask::query()->firstOrFail();
        $this->assertSame(OperationsTaskSeverity::Critical, $task->severity);
        $this->assertSame(2, $this->admin->notifications()->count());

        // Herabstufung senkt die Severity NICHT (kein Flattern).
        $this->service->report($this->signal(['severity' => OperationsTaskSeverity::Warning]));
        $this->assertSame(OperationsTaskSeverity::Critical, $task->fresh()?->severity);
    }

    public function test_resolve_closes_active_task_and_new_incident_reopens(): void {
        $this->service->report($this->signal());
        $this->service->resolve('backup_overdue');

        $task = OperationsTask::query()->firstOrFail();
        $this->assertSame(OperationsTaskStatus::Resolved, $task->status);
        $this->assertNotNull($task->resolved_at);

        // Neuer Vorfall mit demselben Schlüssel: Zeile wird recycelt + neu gemeldet.
        $this->service->report($this->signal());
        $this->assertDatabaseCount('operations_tasks', 1);
        $this->assertSame(OperationsTaskStatus::Open, $task->fresh()?->status);
        $this->assertSame(2, $this->admin->notifications()->count());
    }

    public function test_expired_snooze_reopens_and_renotifies(): void {
        $this->service->report($this->signal());
        OperationsTask::query()->firstOrFail()->update([
            'status' => OperationsTaskStatus::Snoozed,
            'snoozed_until' => CarbonImmutable::now()->subDay(),
        ]);

        $this->service->report($this->signal());

        $task = OperationsTask::query()->firstOrFail();
        $this->assertSame(OperationsTaskStatus::Open, $task->status);
        $this->assertNull($task->snoozed_until);
        $this->assertSame(2, $this->admin->notifications()->count());
    }

    public function test_ignored_task_stays_ignored_and_silent(): void {
        $this->service->report($this->signal());
        OperationsTask::query()->firstOrFail()->update(['status' => OperationsTaskStatus::Ignored]);

        $this->service->report($this->signal());

        $this->assertSame(OperationsTaskStatus::Ignored, OperationsTask::query()->firstOrFail()->status);
        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_info_signal_notifies_without_creating_task(): void {
        $this->service->report($this->signal([
            'type' => OperationsTaskType::UpdateAvailable,
            'dedupeKey' => 'update_available:app',
            'severity' => OperationsTaskSeverity::Info,
        ]));

        $this->assertDatabaseCount('operations_tasks', 0);
        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_org_scoped_signal_is_not_system(): void {
        $task = $this->service->report($this->signal([
            'organizationId' => (int) $this->admin->organization_id,
            'dedupeKey' => 'connection_failing:email:1',
            'type' => OperationsTaskType::ConnectionFailing,
        ]));

        $this->assertNotNull($task);
        $this->assertFalse($task->is_system);
    }
}
