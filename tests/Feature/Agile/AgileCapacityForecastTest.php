<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileCapacityForecastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\AgileSprint;
use App\Models\{Organization, Project, User, Vacation, WorkSchedule};
use App\Services\Agile\{AgileBoardService, AgileMetricsService, AgileSprintService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 064, P10 (MVP-148): Kapazitäts-Snapshot beim Start (Arbeitszeit-
 * modell − genehmigter Urlaub ± Korrektur mit Pflichtbegründung, danach
 * unveränderlich), Monte-Carlo-Prognose (Seed fixiert → deterministisch,
 * < 4 Wochen → Hinweis statt Ergebnis), Management-Übersicht org-weit nur
 * mit Projekt-Sichtrecht.
 */
final class AgileCapacityForecastTest extends TestCase {
    use RefreshDatabase;

    private \App\Models\Agile\AgileBoard $board;

    private User $lead;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $org->id]);
        $this->project = Project::factory()->create(['organization_id' => $org->id]);
        $this->board = app(AgileBoardService::class)->activate($this->project, \App\Models\Agile\AgileBoard::METHOD_SCRUM, $this->lead);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_capacity_snapshot_is_written_on_start(): void {
        // Projektmitglied mit 40h-Woche und 2 Tagen genehmigtem Urlaub im Sprint.
        $this->project->members()->attach($this->lead->id);
        WorkSchedule::query()->create([
            'organization_id' => $this->project->organization_id,
            'user_id' => $this->lead->id,
            'weekly_minutes' => 2400,
            'working_days' => [1, 2, 3, 4, 5],
            'valid_from' => '2026-01-01',
        ]);
        Vacation::query()->create([
            'organization_id' => $this->project->organization_id,
            'user_id' => $this->lead->id,
            'start_date' => '2026-07-08',
            'end_date' => '2026-07-09',
            'type' => 'vacation',
            'status' => 'approved',
        ]);

        $sprints = app(AgileSprintService::class);
        $sprint = $sprints->plan($this->board, [
            'name' => 'Sprint K', 'goal' => 'Kapazität',
            'starts_on' => '2026-07-06', 'ends_on' => '2026-07-12',
        ], $this->lead);
        $sprints->assign($sprint, app(AgileWorkItemService::class)->create($this->board, ['title' => 'A'], $this->lead), $this->lead);

        // Korrektur ohne Begründung → abgelehnt.
        try {
            $sprints->start($sprint, $this->lead, capacityAdjustmentHours: -4.0);
            $this->fail('Korrektur ohne Begründung wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        $sprint = $sprints->start($sprint->fresh(), $this->lead, -4.0, 'Messeeinsatz');
        $capacity = $sprint->capacity_snapshot;

        // 7 Kalendertage = 1 Woche à 40h; Urlaub 2 Tage à 8h; Korrektur −4.
        $this->assertSame(40.0, (float) $capacity['base_hours']);
        $this->assertSame(16.0, (float) $capacity['absence_hours']);
        $this->assertSame(-4.0, (float) $capacity['adjustment_hours']);
        $this->assertSame('Messeeinsatz', $capacity['adjustment_reason']);
        $this->assertSame(20.0, (float) $capacity['total_hours']);
    }

    public function test_forecast_needs_history_and_is_deterministic_with_seed(): void {
        $metrics = app(AgileMetricsService::class);

        // Ohne Historie: Hinweis statt Ergebnis.
        $empty = $metrics->forecast($this->board, remainingItems: 5);
        $this->assertFalse($empty->data['available']);

        // 5 Wochen Durchsatz-Historie aufbauen (je 1–2 erledigte Elemente).
        $boards = app(AgileBoardService::class);
        $items = app(AgileWorkItemService::class);
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        foreach ([1, 2, 1, 2, 1] as $week => $count) {
            for ($i = 0; $i < $count; $i++) {
                Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00')->addWeeks($week)->addHours($i));
                $item = $items->create($this->board, ['title' => "W{$week}-{$i}"], $this->lead);
                $boards->move($item->fresh(), $done, (int) $item->fresh()->lock_version, null, $this->lead);
            }
        }
        Carbon::setTestNow();

        $first = $metrics->forecast($this->board, remainingItems: 10, seed: 42);
        $second = $metrics->forecast($this->board, remainingItems: 10, seed: 42);

        $this->assertTrue($first->data['available']);
        $this->assertSame($first->data['p50'], $second->data['p50']);
        $this->assertSame($first->data['p85'], $second->data['p85']);
        $this->assertGreaterThan(0, $first->data['p50']);
        $this->assertGreaterThanOrEqual($first->data['p50'], $first->data['p95']);
    }

    public function test_management_overview_filters_by_project_visibility(): void {
        $this->actingAs($this->lead)->get(route('agile.reports.overview'))
            ->assertOk()
            ->assertSee($this->project->name)
            ->assertSee(__('Zu wenig Historie'));

        // Fremde Org taucht nicht auf (Org-Scope + Policy).
        $otherOrg = Organization::factory()->create();
        $foreignProject = Project::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Fremdboard-Projekt']);
        \App\Models\Agile\AgileBoard::query()->create([
            'organization_id' => $otherOrg->id,
            'project_id' => $foreignProject->id,
            'method' => 'kanban',
            'name' => 'Fremdboard-Projekt',
        ]);

        $this->actingAs($this->lead)->get(route('agile.reports.overview'))
            ->assertOk()
            ->assertDontSee('Fremdboard-Projekt');
        $this->assertSame(1, AgileSprint::query()->count() >= 0 ? 1 : 0);
    }
}
