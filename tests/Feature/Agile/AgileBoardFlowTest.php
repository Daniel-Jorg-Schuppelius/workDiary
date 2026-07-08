<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBoardFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\{AgileAcceptanceCriterion, AgileEvent, AgileWorkItem};
use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileFlowException, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 064, P3 (MVP-141): WIP hart/Override mit Pflichtgrund,
 * Kategorie-Sync in beide Richtungen (inkl. Reopen), Kriterien-Sperre
 * nach done, Blockierpflichtgrund, Move ohne JS (Form-Fallback).
 */
final class AgileBoardFlowTest extends TestCase {
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

    private function item(string $title = 'Story'): AgileWorkItem {
        return app(AgileWorkItemService::class)->create($this->board, ['title' => $title], $this->lead);
    }

    public function test_wip_limit_is_enforced_and_overridable_with_reason(): void {
        $service = app(AgileBoardService::class);
        $inProgress = $this->board->columns()->where('name', 'In Arbeit')->firstOrFail();
        $inProgress->update(['wip_limit' => 1]);

        $a = $this->item('A');
        $b = $this->item('B');
        $service->move($a, $inProgress, (int) $a->fresh()->lock_version, null, $this->lead);

        // Hart: ohne Override 422-Weg.
        try {
            $service->move($b, $inProgress, (int) $b->fresh()->lock_version, null, $this->lead);
            $this->fail('WIP-Limit wurde nicht durchgesetzt.');
        } catch (AgileFlowException $e) {
            $this->assertSame('wip', $e->reason);
        }

        // Override braucht Grund.
        try {
            $service->move($b, $inProgress, (int) $b->fresh()->lock_version, '', $this->lead, mayOverride: true);
            $this->fail('Override ohne Grund wurde akzeptiert.');
        } catch (AgileFlowException) {
        }

        $service->move($b, $inProgress, (int) $b->fresh()->lock_version, 'Eilauftrag', $this->lead, mayOverride: true);
        $this->assertSame(1, AgileEvent::query()->where('event', 'override.wip')->count());
    }

    public function test_done_move_syncs_task_status_and_reopen_syncs_back(): void {
        $service = app(AgileBoardService::class);
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        $item = $this->item();

        $service->move($item, $done, (int) $item->fresh()->lock_version, null, $this->lead);
        $this->assertSame('done', (string) $item->task()->firstOrFail()->getAttribute('status')?->value);

        // Rückrichtung: Task-Reopen außerhalb des Boards schiebt die Karte
        // in die erste Spalte der Zielkategorie (task_sync-Event).
        $item->task()->firstOrFail()->forceFill(['status' => 'open'])->save();

        $fresh = $item->fresh(['column']);
        $this->assertSame('open', $fresh->column?->category?->value);
        $this->assertSame('Bereit', $fresh->column?->name);
        $this->assertSame(
            1,
            AgileEvent::query()->where('event', 'column.moved')
                ->where('payload', 'like', '%task_sync%')->count(),
        );
    }

    public function test_open_criteria_block_done_without_override(): void {
        $service = app(AgileBoardService::class);
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        $item = $this->item();
        AgileAcceptanceCriterion::query()->create([
            'organization_id' => $item->organization_id,
            'work_item_id' => $item->id,
            'position' => 1,
            'text' => 'Muss geprüft sein',
        ]);

        try {
            $service->move($item, $done, (int) $item->fresh()->lock_version, null, $this->lead);
            $this->fail('Offene Kriterien wurden ignoriert.');
        } catch (AgileFlowException $e) {
            $this->assertSame('criteria', $e->reason);
        }

        $service->move($item, $done, (int) $item->fresh()->lock_version, 'PO-Entscheid', $this->lead, mayOverride: true);
        $this->assertSame(1, AgileEvent::query()->where('event', 'override.criteria')->count());
    }

    public function test_block_requires_reason_and_card_stays(): void {
        $service = app(AgileBoardService::class);
        $item = $this->item();
        $column = $this->board->columns()->orderBy('position')->firstOrFail();
        $service->move($item, $column, (int) $item->fresh()->lock_version, null, $this->lead);

        $this->expectException(\InvalidArgumentException::class);
        $service->block($item->fresh(), '   ');
    }

    public function test_column_management_add_rename_and_resequence(): void {
        $service = app(AgileBoardService::class);

        // Review → „QA" umbenennen und an Position 2 einreihen.
        $review = $this->board->columns()->where('name', 'Review')->firstOrFail();
        $service->saveColumn($this->board, ['name' => 'QA', 'category' => 'in_progress', 'wip_limit' => 2, 'position' => 2], $review, $this->lead);

        $fresh = $this->board->fresh(['columns']);
        $this->assertSame(['Bereit', 'QA', 'In Arbeit', 'Erledigt'], $fresh->columns->pluck('name')->all());
        $this->assertSame([1, 2, 3, 4], $fresh->columns->pluck('position')->map(fn($p) => (int) $p)->all());

        // Neue Spalte via HTTP ans Ende.
        $this->actingAs($this->lead)
            ->post(route('agile.columns.store', [$this->project]), ['name' => 'Warten', 'category' => 'in_progress'])
            ->assertRedirect(route('agile.board', $this->project));
        $this->assertSame(5, $this->board->columns()->count());
        $this->assertSame('Warten', $this->board->columns()->reorder('position', 'desc')->firstOrFail()->name);
    }

    public function test_column_delete_only_empty_and_categories_stay_covered(): void {
        $service = app(AgileBoardService::class);
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();

        // Letzte done-Spalte: weder löschen noch umkategorisieren.
        try {
            $service->deleteColumn($done, $this->lead);
            $this->fail('Letzte done-Spalte wurde gelöscht.');
        } catch (\RuntimeException) {
        }
        try {
            $service->saveColumn($this->board, ['name' => (string) $done->name, 'category' => 'open'], $done, $this->lead);
            $this->fail('Letzte done-Spalte wurde umkategorisiert.');
        } catch (\RuntimeException) {
        }

        // Nicht-leere Spalte ist gesperrt.
        $inProgress = $this->board->columns()->where('name', 'In Arbeit')->firstOrFail();
        $item = $this->item();
        $service->move($item, $inProgress, (int) $item->fresh()->lock_version, null, $this->lead);
        try {
            $service->deleteColumn($inProgress, $this->lead);
            $this->fail('Nicht-leere Spalte wurde gelöscht.');
        } catch (\RuntimeException) {
        }

        // Leere in_progress-Spalte geht — Positionen rücken lückenlos nach.
        $review = $this->board->columns()->where('name', 'Review')->firstOrFail();
        $service->deleteColumn($review, $this->lead);
        $this->assertSame([1, 2, 3], $this->board->columns()->pluck('position')->map(fn($p) => (int) $p)->all());
    }

    public function test_move_without_js_via_form_fallback(): void {
        $item = $this->item();
        $inProgress = $this->board->columns()->where('name', 'In Arbeit')->firstOrFail();

        $this->actingAs($this->lead)
            ->patch(route('agile.items.move', [$this->project, $item]), [
                'column' => $inProgress->sqid,
                'lock_version' => $item->fresh()->lock_version,
            ])->assertRedirect(route('agile.board', $this->project));

        $this->assertSame((int) $inProgress->id, (int) $item->fresh()->column_id);

        // JSON-Weg liefert neue lock_version + Spaltenzähler.
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        $response = $this->actingAs($this->lead)
            ->patchJson(route('agile.items.move', [$this->project, $item]), [
                'column' => $done->sqid,
                'lock_version' => $item->fresh()->lock_version,
            ]);
        $response->assertOk()->assertJsonStructure(['lock_version', 'column_counts']);
    }
}
