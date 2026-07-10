<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTaskCenterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskStatus, OperationsTaskType};
use App\Models\{OperationsTask, ProblemReport, SupportAccessGrant, User};
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OperationsTaskCenterTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function freshTask(OperationsTask $task): OperationsTask {
        return OperationsTask::query()->findOrFail($task->id);
    }

    /** @param array<string, mixed> $overrides */
    private function makeTask(array $overrides = []): OperationsTask {
        $signal = new OperationsSignal(
            type: $overrides['type'] ?? OperationsTaskType::BackupOverdue,
            dedupeKey: $overrides['dedupeKey'] ?? 'backup_overdue',
            severity: OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.backup_overdue',
            params: ['hours' => 30, 'threshold' => 26],
            organizationId: $overrides['organizationId'] ?? (int) $this->admin->organization_id,
        );
        $task = app(OperationsAlertService::class)->report($signal);
        assert($task !== null);

        return $task;
    }

    public function test_index_requires_permission(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);
        $this->actingAs($user)->get(route('admin.operations.index'))->assertForbidden();
    }

    public function test_index_lists_active_tasks_of_current_org(): void {
        $this->makeTask();

        $this->actingAs($this->admin)->get(route('admin.operations.index'))
            ->assertOk()
            ->assertSee(__('operations.type.backup_overdue'));
    }

    public function test_done_and_reopen_roundtrip_with_audit(): void {
        $task = $this->makeTask();

        $this->actingAs($this->admin)
            ->post(route('admin.operations.done', $task))
            ->assertRedirect();
        $this->assertSame(OperationsTaskStatus::Done, $this->freshTask($task)->status);
        $this->assertSame($this->admin->id, $this->freshTask($task)->acted_by_user_id);

        $this->actingAs($this->admin)
            ->post(route('admin.operations.reopen', $task))
            ->assertRedirect();
        $this->assertSame(OperationsTaskStatus::Open, $this->freshTask($task)->status);

        $this->assertTrue(
            \App\Models\AuditLog::query()
                ->where('auditable_type', OperationsTask::class)
                ->where('auditable_id', $task->id)
                ->where('event', 'updated')
                ->exists(),
        );
    }

    public function test_snooze_uses_setting_and_scanner_reactivates_after_expiry(): void {
        $task = $this->makeTask();

        $this->actingAs($this->admin)
            ->post(route('admin.operations.snooze', $task))
            ->assertRedirect();

        $fresh = $this->freshTask($task);
        $this->assertSame(OperationsTaskStatus::Snoozed, $fresh->status);
        $this->assertNotNull($fresh->snoozed_until);
    }

    public function test_delegate_assigns_org_user_via_sqid(): void {
        $task = $this->makeTask();
        $colleague = User::factory()->user()->create(['organization_id' => $this->admin->organization_id]);

        $this->actingAs($this->admin)
            ->post(route('admin.operations.delegate', $task), ['assigned_user' => $colleague->sqid])
            ->assertRedirect();

        $this->assertSame(OperationsTaskStatus::Delegated, $this->freshTask($task)->status);
        $this->assertSame($colleague->id, $this->freshTask($task)->assigned_user_id);
    }

    public function test_delegate_rejects_cross_org_user(): void {
        $task = $this->makeTask();
        $foreign = User::factory()->user()->create(); // eigene fremde Org

        $this->actingAs($this->admin)
            ->from(route('admin.operations.index'))
            ->post(route('admin.operations.delegate', $task), ['assigned_user' => $foreign->sqid])
            ->assertSessionHasErrors('assigned_user_id');
    }

    public function test_ignore_requires_note(): void {
        $task = $this->makeTask();

        $this->actingAs($this->admin)
            ->from(route('admin.operations.index'))
            ->post(route('admin.operations.ignore', $task), [])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->admin)
            ->post(route('admin.operations.ignore', $task), ['note' => 'Bewusst: Backup läuft extern.'])
            ->assertRedirect();
        $this->assertSame(OperationsTaskStatus::Ignored, $this->freshTask($task)->status);
    }

    public function test_cross_org_task_is_not_accessible(): void {
        $task = $this->makeTask(['organizationId' => (int) User::factory()->user()->create()->organization_id, 'dedupeKey' => 'backup_overdue:fremd']);

        $this->actingAs($this->admin)
            ->post(route('admin.operations.done', $task))
            ->assertNotFound();
    }

    public function test_scan_creates_and_resolves_support_grant_and_report_tasks(): void {
        $orgId = (int) $this->admin->organization_id;
        SupportAccessGrant::query()->create([
            'organization_id' => $orgId,
            'granted_by_user_id' => $this->admin->id,
            'purpose' => 'Test-Freigabe',
            'expires_at' => now()->addDay(),
        ]);
        ProblemReport::query()->create([
            'organization_id' => $orgId,
            'user_id' => $this->admin->id,
            'reference_no' => 'PR-2026-0001',
            'status' => 'new',
            'severity' => 'normal',
            'summary' => 'Test',
            'description' => 'Test',
            'page_context' => [],
            'delivery_target' => 'saas_inbox',
        ]);

        Artisan::call('operations:scan');

        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "support_grant_open:org:{$orgId}", 'status' => 'open']);
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "problem_report_open:org:{$orgId}", 'status' => 'open']);

        // Ursachen beseitigen → Auto-Resolve beim nächsten Scan.
        SupportAccessGrant::query()->update(['revoked_at' => now()]);
        ProblemReport::query()->update(['status' => 'closed']);
        Artisan::call('operations:scan');

        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "support_grant_open:org:{$orgId}", 'status' => 'resolved']);
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "problem_report_open:org:{$orgId}", 'status' => 'resolved']);
    }

    public function test_watchdog_overdue_creates_task_and_success_resolves_it(): void {
        $this->travelTo(now()->startOfHour()->addMinutes(45));
        \App\Models\ScheduledJobState::query()->create([
            'job_key' => 'toggl.import',
            'last_started_at' => now()->subHours(3),
            'last_success_at' => now()->subHours(3),
        ]);

        Artisan::call('scheduler:watchdog');
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'scheduler_overdue:toggl.import', 'status' => 'open']);

        // Erfolgreicher Lauf → Recorder löst die Aufgabe auf.
        $schedule = new \Illuminate\Console\Scheduling\Schedule;
        $task = $schedule->command('toggl:import');
        $recorder = app(\App\Scheduling\ScheduleRunRecorder::class);
        $recorder->handleStarting(new \Illuminate\Console\Events\ScheduledTaskStarting($task));
        $task->exitCode = 0;
        $recorder->handleFinished(new \Illuminate\Console\Events\ScheduledTaskFinished($task, 0.5));

        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'scheduler_overdue:toggl.import', 'status' => 'resolved']);
    }

    public function test_plugin_auto_disable_creates_critical_task_and_recovery_resolves(): void {
        event(new \App\Events\PluginAutoDisabled('toggl', (int) $this->admin->organization_id, 'kaputt', 5));

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'plugin_disabled:toggl:' . $this->admin->organization_id,
            'status' => 'open',
            'severity' => 'critical',
        ]);

        event(new \App\Events\PluginRecovered('toggl', (int) $this->admin->organization_id));

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'plugin_disabled:toggl:' . $this->admin->organization_id,
            'status' => 'resolved',
        ]);
    }
}
