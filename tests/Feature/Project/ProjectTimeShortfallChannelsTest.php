<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallChannelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\Project\ShortfallStatus;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Notifications\Project\ProjectTimeShortfallNotification;
use App\Services\Project\EarlyWarnings\ProjectTimeShortfallWarningSource;
use App\Services\Project\Management\ProjectTimeShortfallKpiSource;
use App\Services\Project\WorkQueue\ProjectTimeShortfallWorkItemSource;
use App\Services\TimeApproval\{DayCloseService, DayClosureValidator};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Meldewege der Projekt-Sollzeiten (Feature 194, MVP-1469): Benachrichtigung
 * der Projektleitung je Projekt und Tag, Arbeitsvorrat, Tagesabschluss,
 * Leitungskennzahl, Frühwarnung, Bestätigen, Plan/Ist-Sicht.
 */
class ProjectTimeShortfallChannelsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $anna;

    private User $ben;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow('2030-03-14 12:00:00');
        $this->admin = $this->orgAdmin();
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->ben = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Ben Muster']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schule Nord']);
        $this->project->members()->attach([$this->anna->id, $this->ben->id]);
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => $weekday, 'target_minutes' => 480]);
        }
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'minutes' => 300]);
    }

    public function test_command_notifies_leads_once_per_project_and_day(): void {
        Notification::fake();

        $this->artisan('projects:check-time-targets', ['--date' => '2030-03-11', '--days' => 1, '--organization' => $this->organization->id])
            ->expectsOutputToContain('Schule Nord: 2 neu unter Soll')
            ->assertSuccessful();

        Notification::assertSentTo($this->admin, ProjectTimeShortfallNotification::class, static fn (ProjectTimeShortfallNotification $n): bool => $n->count === 2 && $n->projectName === 'Schule Nord');
        Notification::assertCount(1);
        Notification::assertNotSentTo($this->anna, ProjectTimeShortfallNotification::class);
        $this->assertSame(2, ProjectTimeShortfall::query()->whereNotNull('notified_at')->count());
    }

    public function test_work_queue_kpi_and_early_warning_read_the_findings(): void {
        foreach (['2030-03-11', '2030-03-12', '2030-03-13'] as $date) {
            ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => $date]);
        }
        ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->ben->id, 'date' => '2030-03-12', 'status' => ShortfallStatus::Resolved->value]);

        $items = iterator_to_array(app(ProjectTimeShortfallWorkItemSource::class)->items($this->admin, $this->organization), false);
        $this->assertCount(1, $items);
        $this->assertSame(3, $items[0]->count, 'Ein Vorgang je Projekt, erledigte Befunde zählen nicht');
        $own = iterator_to_array(app(ProjectTimeShortfallWorkItemSource::class)->items($this->anna, $this->organization), false);
        $this->assertCount(1, $own);
        $this->assertTrue($own[0]->personal);

        $kpis = app(ProjectTimeShortfallKpiSource::class)->kpis($this->admin, $this->organization, CarbonImmutable::parse('2030-03-01'), CarbonImmutable::parse('2030-03-31'));
        $this->assertCount(1, $kpis);
        $this->assertSame('3', $kpis[0]->value);
        $this->assertSame('warning', $kpis[0]->tone);
        $this->assertSame([], app(ProjectTimeShortfallKpiSource::class)->kpis($this->anna, $this->organization, CarbonImmutable::parse('2030-03-01'), CarbonImmutable::parse('2030-03-31')));

        $warnings = app(ProjectTimeShortfallWarningSource::class)->warnings($this->organization);
        $this->assertCount(1, $warnings);
        $this->assertSame(3, $warnings[0]->params['count']);
        $this->assertTrue($warnings[0]->subject->is($this->project));
    }

    public function test_acknowledge_marks_the_finding_and_requires_the_permission(): void {
        $shortfall = ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11']);

        $this->actingAs($this->anna)->post(route('projects.time-shortfalls.acknowledge', [$this->project, $shortfall]))->assertForbidden();
        $this->actingAs($this->admin)->post(route('projects.time-shortfalls.acknowledge', [$this->project, $shortfall]))->assertRedirect();

        $fresh = $shortfall->fresh();
        $this->assertSame(ShortfallStatus::Acknowledged, $fresh->status);
        $this->assertSame((int) $this->admin->id, (int) $fresh->acknowledged_by);
    }

    public function test_day_close_lists_the_project_shortfall_as_soft_warning(): void {
        $context = app(DayCloseService::class)->context($this->anna, CarbonImmutable::parse('2030-03-11'));

        $codes = array_column($context['issues'], 'code');
        $this->assertContains(DayClosureValidator::CHECK_PROJECT_TARGET_SHORTFALL, $codes);
        $issue = collect($context['issues'])->firstWhere('code', DayClosureValidator::CHECK_PROJECT_TARGET_SHORTFALL);
        $this->assertSame(['Schule Nord', 480, 300, 180], array_values($issue['meta']));
        $this->assertFalse($context['hasBlocking'], 'Die Unterschreitung blockiert den Tagesabschluss nicht');
        $this->assertStringContainsString('Schule Nord', app(DayCloseService::class)->makeValidator()->messageFor($issue));
    }

    public function test_plan_ist_project_persons_view_shows_target_and_booked_time(): void {
        ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11']);

        $html = $this->actingAs($this->admin)
            ->get(route('reports.plan-ist.project-persons', ['from' => '2030-03-11', 'to' => '2030-03-15']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Anna Beispiel', $html);
        $this->assertStringContainsString('Ben Muster', $html);
        $this->assertStringContainsString('Projekt je Person', $html);
        // Anna: 5 × 8 h Soll, 5 h gebucht; Ben: 40 h Soll, nichts gebucht.
        $this->assertStringContainsString('40,0 h', $html);
        $this->assertStringContainsString('5,0 h', $html);
    }
}
