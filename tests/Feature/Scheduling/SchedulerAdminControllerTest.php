<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerAdminControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Scheduling;

use App\Enums\Scheduling\JobRunStatus;
use App\Models\{ScheduledJobOverride, ScheduledJobState, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchedulerAdminControllerTest extends TestCase {
    // Der Scheduler steuert die Jobs der ganzen Installation (Overrides mit
    // organization_id = null) — seit dem Sicherheitsscan 2026-08-23 (S-02)
    // ausschließlich für Plattform-Betreiber.

    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_index_requires_permission(): void {
        $this->get(route('admin.scheduler.index'))->assertRedirect(route('login'));

        $user = User::factory()->user()->create();
        $this->actingAs($user)->get(route('admin.scheduler.index'))->assertForbidden();
    }

    public function test_index_lists_registry_jobs_for_admin(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee('toggl.import')
            ->assertSee('scheduler.watchdog')
            ->assertSee('archive:run');
    }

    /**
     * Liste zeigt die wirksame, verschobene Zeit samt Herkunft; der Umplanen-
     * Dialog die eingestellte — sonst schriebe Speichern die Verschiebung fest.
     */
    public function test_operating_window_is_explained_and_dialog_keeps_the_configured_time(): void {
        \App\Support\Setting::set('scheduler.operating_window_start', '08:00', \App\Settings\SettingScope::System);
        \App\Support\Setting::set('scheduler.operating_window_end', '00:00', \App\Settings\SettingScope::System);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee('08:00–24:00')
            ->assertSee('37 8 * * *')
            ->assertSee(__('scheduler.source.shifted', ['time' => '02:30']));

        $this->actingAs($admin)
            ->get(route('admin.scheduler.edit', ['job' => 'audit.verify']))
            ->assertOk()
            ->assertSee('value="02:30"', false);
    }

    /** Produktionsmeldung 2026-09-19: Die Spalte zeigte nur „Täglich um“ — ohne Zeit und Tag. */
    public function test_index_shows_the_complete_plan_with_time_and_day(): void {
        \App\Support\Setting::set('scheduler.operating_window_start', '08:00', \App\Settings\SettingScope::System);
        \App\Support\Setting::set('scheduler.operating_window_end', '00:00', \App\Settings\SettingScope::System);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.scheduler.update', ['job' => 'inventory.cycle_counts']), [
                'cadence_type' => 'monthlyOn',
                'day' => 15,
                'time' => '10:30',
            ])
            ->assertRedirect(route('admin.scheduler.index'));

        $this->actingAs($admin)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee('Täglich um 08:37')           // audit.verify, 02:30 verschoben
            ->assertSee('Jeden Montag um 09:40')      // finance.open_times_digest, 06:40 verschoben
            ->assertSee('Monatlich am 15. um 10:30'); // inventory.cycle_counts, Override
    }

    /** Produktionsmeldung 2026-09-19: „running“ stand roh (englisch) im Ergebnis-Badge. */
    public function test_run_status_badges_are_translated(): void {
        foreach (JobRunStatus::cases() as $status) {
            $this->assertStringNotContainsString('scheduler.', $status->label());
        }
        ScheduledJobState::query()->create(['job_key' => 'toggl.import', 'last_started_at' => now(), 'last_status' => JobRunStatus::Running]);
        ScheduledJobState::query()->create(['job_key' => 'billbee.sync', 'last_started_at' => now(), 'last_status' => JobRunStatus::Skipped]);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee(JobRunStatus::Running->label())
            ->assertSee(JobRunStatus::Skipped->label());
    }

    public function test_index_filters_by_search_criticality_status_source_and_pause(): void {
        $admin = User::factory()->platformAdmin()->create();
        ScheduledJobState::query()->create([
            'job_key' => 'audit.verify',
            'last_started_at' => now(),
            'last_status' => JobRunStatus::Failed,
            'consecutive_failures' => 2,
        ]);
        $this->actingAs($admin)->post(route('admin.scheduler.pause', ['job' => 'toggl.import']));
        $this->actingAs($admin)->put(route('admin.scheduler.update', ['job' => 'finance.open_times_digest']), [
            'cadence_type' => 'dailyAt',
            'time' => '10:00',
        ]);

        $edit = static fn(string $job): string => route('admin.scheduler.edit', ['job' => $job]);
        $index = fn(array $query) => $this->actingAs($admin)->get(route('admin.scheduler.index', $query))->assertOk();

        $index(['q' => 'toggl'])->assertSee($edit('toggl.import'), false)->assertDontSee($edit('audit.verify'), false);
        $index(['criticality' => 'integration'])->assertSee($edit('billbee.sync'), false)->assertDontSee($edit('audit.verify'), false);
        $index(['status' => 'failed'])->assertSee($edit('audit.verify'), false)->assertDontSee($edit('billbee.sync'), false);
        $index(['status' => 'never_ran'])->assertSee($edit('billbee.sync'), false)->assertDontSee($edit('audit.verify'), false);
        $index(['source' => 'override'])->assertSee($edit('finance.open_times_digest'), false)->assertDontSee($edit('audit.verify'), false);
        $index(['paused' => 1])->assertSee($edit('toggl.import'), false)->assertDontSee($edit('billbee.sync'), false);
        $index(['q' => 'gibt-es-nicht'])->assertSee(__('scheduler.empty.title'));
    }

    public function test_index_sorts_server_side_with_jobs_without_value_last(): void {
        $admin = User::factory()->platformAdmin()->create();
        ScheduledJobState::query()->create(['job_key' => 'audit.verify', 'last_started_at' => now()->subDay(), 'consecutive_failures' => 1]);
        ScheduledJobState::query()->create(['job_key' => 'billbee.sync', 'last_started_at' => now(), 'consecutive_failures' => 4]);

        $edit = static fn(string $job): string => route('admin.scheduler.edit', ['job' => $job]);
        $index = fn(array $query) => $this->actingAs($admin)->get(route('admin.scheduler.index', $query))->assertOk();

        $index(['sort' => 'failures', 'dir' => 'desc'])->assertSeeInOrder([$edit('billbee.sync'), $edit('audit.verify')], false);
        $index(['sort' => 'failures', 'dir' => 'asc'])->assertSeeInOrder([$edit('audit.verify'), $edit('billbee.sync')], false);
        // toggl.import lief nie — steht in beiden Richtungen hinter den gelaufenen Jobs.
        $index(['sort' => 'last_run', 'dir' => 'desc'])->assertSeeInOrder([$edit('billbee.sync'), $edit('audit.verify'), $edit('toggl.import')], false);
        $index(['sort' => 'last_run', 'dir' => 'asc'])->assertSeeInOrder([$edit('audit.verify'), $edit('billbee.sync'), $edit('toggl.import')], false);
    }

    /** Rückmeldung 2026-09-19: Ein Testlauf aus der gefilterten Liste setzte Filter und Sortierung zurück. */
    public function test_actions_return_to_the_filtered_and_sorted_list(): void {
        Queue::fake();
        $admin = User::factory()->platformAdmin()->create();
        $list = ['dir' => 'desc', 'sort' => 'next_due', 'status' => 'never_ran']; // fullUrl() sortiert die Query

        $this->actingAs($admin)->get(route('admin.scheduler.index', $list))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.scheduler.test-run', ['job' => 'plugin.healthcheck']))
            ->assertRedirect(route('admin.scheduler.index', $list));

        $this->actingAs($admin)->get(route('admin.scheduler.edit', ['job' => 'toggl.import']))->assertOk();
        $this->actingAs($admin)
            ->put(route('admin.scheduler.update', ['job' => 'toggl.import']), ['cadence_type' => 'hourly'])
            ->assertRedirect(route('admin.scheduler.index', $list));
    }

    public function test_index_without_operating_window_hints_at_the_setting(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee(__('scheduler.window.none'))
            ->assertDontSee(__('scheduler.source.shifted', ['time' => '02:30']));
    }

    public function test_pause_resume_and_reset_roundtrip(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.scheduler.pause', ['job' => 'toggl.import']))
            ->assertRedirect(route('admin.scheduler.index'));

        $this->assertDatabaseHas('scheduled_job_overrides', [
            'job_key' => 'toggl.import',
            'enabled' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.scheduler.resume', ['job' => 'toggl.import']))
            ->assertRedirect(route('admin.scheduler.index'));

        $this->assertDatabaseCount('scheduled_job_overrides', 0);
    }

    public function test_update_within_allowed_cadence(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.scheduler.update', ['job' => 'toggl.import']), [
                'cadence_type' => 'everyFifteenMinutes',
            ])
            ->assertRedirect(route('admin.scheduler.index'));

        $override = ScheduledJobOverride::query()->where('job_key', 'toggl.import')->firstOrFail();
        $cadence = $override->cadence;
        $this->assertIsArray($cadence);
        $this->assertSame('everyFifteenMinutes', $cadence['type']);
        $this->assertSame($admin->id, $override->updated_by_user_id);
    }

    public function test_update_rejects_disallowed_cadence(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.scheduler.index'))
            ->put(route('admin.scheduler.update', ['job' => 'toggl.import']), [
                'cadence_type' => 'everyMinute',
            ])
            ->assertSessionHasErrors('cadence_type');

        $this->assertDatabaseCount('scheduled_job_overrides', 0);
    }

    public function test_update_rejects_unknown_job(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.scheduler.update', ['job' => 'nicht.registriert']), [
                'cadence_type' => 'hourly',
            ])
            ->assertNotFound();
    }

    public function test_edit_returns_404_for_unknown_job(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.edit', ['job' => 'nicht.registriert']))
            ->assertNotFound();
    }

    public function test_test_run_queues_command_and_writes_audit(): void {
        Queue::fake();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.scheduler.test-run', ['job' => 'plugin.healthcheck']))
            ->assertRedirect(route('admin.scheduler.index'));

        Queue::assertPushed(\Illuminate\Foundation\Console\QueuedCommand::class);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'scheduler.testRun',
        ]);

        // Cooldown: zweiter Testlauf sofort danach wird abgelehnt.
        $this->actingAs($admin)
            ->post(route('admin.scheduler.test-run', ['job' => 'plugin.healthcheck']))
            ->assertSessionHas('error');
    }

    public function test_edit_dialog_shows_allowed_cadences_only(): void {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.scheduler.edit', ['job' => 'toggl.import']))
            ->assertOk()
            ->assertSee('everyFifteenMinutes', false);

        $response->assertDontSee('value="everyMinute"', false);
    }
}
