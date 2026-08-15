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

use App\Models\{ScheduledJobOverride, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchedulerAdminControllerTest extends TestCase {
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
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.index'))
            ->assertOk()
            ->assertSee('toggl.import')
            ->assertSee('scheduler.watchdog')
            ->assertSee('archive:run');
    }

    public function test_pause_resume_and_reset_roundtrip(): void {
        $admin = User::factory()->admin()->create();

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
        $admin = User::factory()->admin()->create();

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
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.scheduler.index'))
            ->put(route('admin.scheduler.update', ['job' => 'toggl.import']), [
                'cadence_type' => 'everyMinute',
            ])
            ->assertSessionHasErrors('cadence_type');

        $this->assertDatabaseCount('scheduled_job_overrides', 0);
    }

    public function test_update_rejects_unknown_job(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.scheduler.update', ['job' => 'nicht.registriert']), [
                'cadence_type' => 'hourly',
            ])
            ->assertNotFound();
    }

    public function test_edit_returns_404_for_unknown_job(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.scheduler.edit', ['job' => 'nicht.registriert']))
            ->assertNotFound();
    }

    public function test_test_run_queues_command_and_writes_audit(): void {
        Queue::fake();
        $admin = User::factory()->admin()->create();

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
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.scheduler.edit', ['job' => 'toggl.import']))
            ->assertOk()
            ->assertSee('everyFifteenMinutes', false);

        $response->assertDontSee('value="everyMinute"', false);
    }
}
