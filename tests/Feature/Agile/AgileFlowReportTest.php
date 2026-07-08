<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileFlowReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\AgileWorkItem;
use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileMetricsService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 064, P9 (MVP-147): Flow-Effizienz nur bei vollständiger
 * Spalten-Klassifikation (sonst Datenqualitäts-Hinweis), Fixture-
 * Nachrechnung (75 %), Backlog-Zu-/Abgang, Fluss-Bericht rendert alle
 * Diagramme mit Tabellen, Berichtsrolle über die Spalten-Verwaltung.
 */
final class AgileFlowReportTest extends TestCase {
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
        $this->board = app(AgileBoardService::class)->activate($this->project, actor: $this->lead);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function classifyColumns(): void {
        $boards = app(AgileBoardService::class);
        foreach ($this->board->columns()->get() as $column) {
            if ($column->category->value === 'done') {
                continue;
            }
            $boards->saveColumn($this->board, [
                'name' => (string) $column->name,
                'category' => $column->category->value,
                'position' => (int) $column->position,
                'report_role' => $column->name === 'In Arbeit' ? 'working' : 'waiting',
            ], $column, $this->lead);
        }
    }

    public function test_flow_efficiency_requires_full_classification(): void {
        $metrics = app(AgileMetricsService::class);

        $result = $metrics->flowEfficiency($this->board);
        $this->assertFalse($result->data['available']);
        $this->assertContains('Bereit', $result->data['unclassified_columns']);

        $this->classifyColumns();
        $this->assertTrue($metrics->flowEfficiency($this->board)->data['available']);
    }

    public function test_flow_efficiency_reproduces_hand_fixture(): void {
        $this->classifyColumns();
        $boards = app(AgileBoardService::class);
        $items = app(AgileWorkItemService::class);

        $inProgress = $this->board->columns()->where('name', 'In Arbeit')->firstOrFail();
        $review = $this->board->columns()->where('name', 'Review')->firstOrFail();
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();

        // 18h Arbeit (In Arbeit=working) + 6h Warten (Review=waiting) → 75 %.
        Carbon::setTestNow('2026-07-01 09:00:00');
        $item = $items->create($this->board, ['title' => 'Fluss'], $this->lead);
        $boards->move($item->fresh(), $inProgress, (int) $item->fresh()->lock_version, null, $this->lead);
        Carbon::setTestNow('2026-07-02 03:00:00');
        $boards->move($item->fresh(), $review, (int) $item->fresh()->lock_version, null, $this->lead);
        Carbon::setTestNow('2026-07-02 09:00:00');
        $boards->move($item->fresh(), $done, (int) $item->fresh()->lock_version, null, $this->lead);

        $result = app(AgileMetricsService::class)->flowEfficiency($this->board);
        $this->assertSame(75.0, $result->data['median']);
        $this->assertSame(1, $result->data['sample_size']);

        // Backlog-Zu-/Abgang derselben Woche: 1 neu, 1 erledigt.
        $flow = app(AgileMetricsService::class)->backlogFlow($this->board);
        $week = Carbon::parse('2026-07-01')->format('o-\WW');
        $this->assertSame(1, $flow->data['weeks'][$week]['added']);
        $this->assertSame(1, $flow->data['weeks'][$week]['done']);
    }

    public function test_flow_report_page_renders_all_charts(): void {
        $this->classifyColumns();
        $boards = app(AgileBoardService::class);
        $items = app(AgileWorkItemService::class);

        $inProgress = $this->board->columns()->where('name', 'In Arbeit')->firstOrFail();
        Carbon::setTestNow('2026-07-06 09:00:00');
        $item = $items->create($this->board, ['title' => 'Aging-Kandidat'], $this->lead);
        $boards->move($item->fresh(), $inProgress, (int) $item->fresh()->lock_version, null, $this->lead);
        Carbon::setTestNow();

        $response = $this->actingAs($this->lead)
            ->get(route('agile.reports.flow', $this->project))
            ->assertOk()
            ->assertSee(__('Kumulatives Flussdiagramm (CFD)'))
            ->assertSee(__('WIP-Historie'))
            ->assertSee(__('Control Chart — Cycle-Time'))
            ->assertSee(__('Blockierdauer je Grund (Pareto)'))
            ->assertSee(__('Aging-WIP (aktuell in Arbeit)'))
            ->assertSee('Aging-Kandidat');

        $this->assertGreaterThanOrEqual(4, substr_count($response->getContent(), '<figure'));
        $this->assertSame(1, AgileWorkItem::query()->count());
    }
}
