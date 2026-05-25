<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Project, Timesheet, User};
use App\Services\Timesheet\Stopwatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class StopwatchWidgetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Widget-Project',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void {
        $this->get(route('dashboard'))->assertRedirect();
    }

    public function test_dashboard_does_not_render_stopwatch_chip_when_idle(): void {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk();

        $html = $response->getContent() ?: '';
        $this->assertStringNotContainsString(route('stopwatch.stop'), $html);
    }

    public function test_dashboard_shows_active_stopwatch_chip_with_running_counter(): void {
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
        ]);
        $entry = app(Stopwatch::class)->start($this->user, $timesheet, null, 'Live-Task');
        $this->assertNotNull($entry->started_at);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Stoppen'))
            ->assertSee('Live-Task');

        $html = $response->getContent() ?: '';
        $this->assertStringContainsString(route('stopwatch.stop'), $html);
        $this->assertStringContainsString('setInterval', $html);
        $this->assertStringContainsString($entry->started_at->toIso8601String(), $html);
    }
}
