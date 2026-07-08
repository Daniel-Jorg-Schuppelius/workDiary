<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileMetricsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\{AgileEvent, AgileSprint, AgileWorkItem};
use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileMetricsService, AgileSprintService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 064, P5 (MVP-143): append-only-Guard, Pflicht-Payload,
 * Kennzahlen deterministisch gegen ein Hand-Fixture (Burndown mit
 * Scope-Zugang, Velocity nur aus Snapshots, Lead/Cycle, Durchsatz,
 * CFD/WIP, Blockierdauer je Grund) — alles NUR aus Events + Snapshots.
 */
final class AgileMetricsTest extends TestCase {
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

    private function item(string $title, ?int $points): AgileWorkItem {
        return app(AgileWorkItemService::class)->create($this->board, [
            'title' => $title,
            'story_points' => $points,
        ], $this->lead);
    }

    public function test_events_are_append_only_and_validate_required_payload(): void {
        Carbon::setTestNow('2026-07-01 09:00:00');
        $item = $this->item('A', 5);

        $event = AgileEvent::query()->where('event', 'backlog.added')->firstOrFail();
        try {
            $event->update(['event' => 'backlog.removed']);
            $this->fail('Event war änderbar.');
        } catch (\RuntimeException) {
        }
        try {
            $event->delete();
            $this->fail('Event war löschbar.');
        } catch (\RuntimeException) {
        }

        // Pflicht-Payload: column.moved ohne from/to wird abgewiesen.
        $this->expectException(\InvalidArgumentException::class);
        AgileEvent::record([
            'organization_id' => $this->board->organization_id,
            'board_id' => $this->board->id,
            'work_item_id' => $item->id,
            'event' => 'column.moved',
            'payload' => ['to' => 1],
            'created_at' => now(),
        ]);
    }

    public function test_metrics_reproduce_hand_fixture(): void {
        $boards = app(AgileBoardService::class);
        $sprints = app(AgileSprintService::class);
        $metrics = app(AgileMetricsService::class);

        $inProgress = $this->board->columns()->where('name', 'In Arbeit')->firstOrFail();
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();

        // 01.07. 09:00 — A(5)+B(3) entstehen, Sprint mit beiden geplant.
        Carbon::setTestNow('2026-07-01 09:00:00');
        $a = $this->item('A', 5);
        $b = $this->item('B', 3);
        $sprint = $sprints->plan($this->board, [
            'name' => 'Sprint 1', 'goal' => 'Fixture',
            'starts_on' => '2026-07-01', 'ends_on' => '2026-07-10',
        ], $this->lead);
        $sprints->assign($sprint, $a, $this->lead);
        $sprints->assign($sprint, $b, $this->lead);

        // 01.07. 10:00 — Start (Commitment 8 Punkte).
        Carbon::setTestNow('2026-07-01 10:00:00');
        $sprint = $sprints->start($sprint, $this->lead);

        // 02.07. 09:00 — A nach „In Arbeit"; 03.07. 09:00 — A erledigt.
        Carbon::setTestNow('2026-07-02 09:00:00');
        $boards->move($a->fresh(), $inProgress, (int) $a->fresh()->lock_version, null, $this->lead);
        Carbon::setTestNow('2026-07-03 09:00:00');
        $boards->move($a->fresh(), $done, (int) $a->fresh()->lock_version, null, $this->lead);

        // 03.07. 12:00 — Scope-Zugang C(2) nach Start.
        Carbon::setTestNow('2026-07-03 12:00:00');
        $c = $this->item('C', 2);
        $sprints->assign($sprint->fresh(), $c, $this->lead);

        // 04.07. — B sechs Stunden blockiert (Grund „Lieferant").
        Carbon::setTestNow('2026-07-04 09:00:00');
        $boards->block($b->fresh(), 'Lieferant', $this->lead);
        Carbon::setTestNow('2026-07-04 15:00:00');
        $boards->unblock($b->fresh(), $this->lead);

        // 05.07. — Abschluss: B+C explizit zurück ins Backlog.
        Carbon::setTestNow('2026-07-05 09:00:00');
        $sprint = $sprints->complete($sprint->fresh(), [
            (int) $b->id => 'backlog',
            (int) $c->id => 'backlog',
        ], $this->lead);

        // Burndown: committed 8; 01.07. → 8, 02.07. → 8, 03.07. → 5 (Scope +2, done −5).
        $burndown = $metrics->burndown($sprint);
        $this->assertSame(AgileMetricsService::METRIC_VERSION, $burndown->metricVersion);
        $this->assertSame(8, $burndown->data['committed']);
        $series = collect($burndown->data['series'])->keyBy('date');
        $this->assertSame(8, $series['2026-07-01']['remaining']);
        $this->assertSame(8, $series['2026-07-02']['remaining']);
        $this->assertSame(5, $series['2026-07-03']['remaining']);
        $this->assertSame(2, $series['2026-07-03']['scope_delta']);
        $this->assertSame(5, $series['2026-07-05']['remaining']);

        // Velocity: nur completion_snapshot (5 done-Punkte), Median 5.
        $velocity = $metrics->velocity($this->board);
        $this->assertSame(5.0, $velocity->data['median']);
        $this->assertSame(5, $velocity->data['sprints'][0]['done_points']);

        // Nachträgliche Punkteänderung ändert die Velocity NICHT.
        app(AgileWorkItemService::class)->setPoints($a->fresh(), 13, $this->lead);
        $this->assertSame(5.0, $metrics->velocity($this->board)->data['median']);

        // Lead 48h (01.07. 09:00 → 03.07. 09:00), Cycle 24h.
        $leadCycle = $metrics->leadCycleTime($this->board);
        $this->assertSame(48.0, $leadCycle->data['lead']['p50']);
        $this->assertSame(24.0, $leadCycle->data['cycle']['p50']);
        $this->assertSame(1, $leadCycle->data['lead']['count']);

        // Durchsatz: 1 Element in der Woche des 03.07.
        $throughput = $metrics->throughput($this->board);
        $week = Carbon::parse('2026-07-03')->format('o-\WW');
        $this->assertSame([$week => 1], $throughput->data['weeks']);

        // CFD/WIP: 02.07. ein Element in Arbeit, 03.07. eines erledigt.
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-07-04');
        $cfd = collect($metrics->cfd($this->board, $from, $to)->data['series'])->keyBy('date');
        $this->assertSame(1, $cfd['2026-07-02']['in_progress']);
        $this->assertSame(0, $cfd['2026-07-02']['done']);
        $this->assertSame(1, $cfd['2026-07-03']['done']);
        $wip = collect($metrics->wipSeries($this->board, $from, $to)->data['series'])->keyBy('date');
        $this->assertSame(1, $wip['2026-07-02']['wip']);
        $this->assertSame(0, $wip['2026-07-03']['wip']);

        // Blockierdauer: 6h unter „Lieferant".
        $blocked = $metrics->blockedDurations($this->board);
        $this->assertSame(6.0, $blocked->data['reasons']['Lieferant']['hours']);
        $this->assertSame(1, $blocked->data['reasons']['Lieferant']['count']);
    }

    public function test_velocity_uses_only_completed_sprints_of_same_board(): void {
        // Fremdes Board (anderes Projekt) mit eigenem completed-Sprint.
        $otherProject = Project::factory()->create(['organization_id' => $this->board->organization_id]);
        $otherBoard = app(AgileBoardService::class)->activate($otherProject, actor: $this->lead);
        AgileSprint::query()->create([
            'organization_id' => $otherBoard->organization_id,
            'board_id' => $otherBoard->id,
            'name' => 'Fremd',
            'status' => AgileSprint::STATUS_COMPLETED,
            'completion_snapshot' => ['done_points' => 99],
            'completed_at' => now(),
        ]);

        $velocity = app(AgileMetricsService::class)->velocity($this->board);
        $this->assertSame([], $velocity->data['sprints']);
        $this->assertSame(0.0, $velocity->data['median']);
    }
}
