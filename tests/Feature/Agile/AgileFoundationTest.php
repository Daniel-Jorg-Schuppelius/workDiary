<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileFoundationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\AgileBoard;
use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileConflictException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 064, P1 (MVP-139): Modul-Gating (423 ohne Lizenz), Aktivierung
 * idempotent (Board + 4 Standardspalten), Policy sperrt fremde Projekte,
 * Einstellungen mit optimistischer Sperre (409 bei lock_version-Konflikt).
 */
final class AgileFoundationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $lead;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
        $this->project = Project::factory()->create(['organization_id' => $this->org->id]);
    }

    public function test_free_plan_is_gated_with_423(): void {
        $this->org->update(['plan' => 'free']);

        $this->actingAs($this->lead)
            ->get(route('agile.board', $this->project))
            ->assertStatus(423);
    }

    public function test_activation_is_idempotent_with_default_columns(): void {
        $service = app(AgileBoardService::class);

        $board = $service->activate($this->project, AgileBoard::METHOD_SCRUM, $this->lead);
        $again = $service->activate($this->project, AgileBoard::METHOD_KANBAN, $this->lead);

        $this->assertSame($board->id, $again->id);
        $this->assertSame('scrum', $again->method, 'Reaktivierung ändert das Board nicht.');
        $this->assertSame(1, AgileBoard::query()->count());
        $this->assertSame(4, $board->columns()->count());
        $this->assertSame(
            ['open', 'in_progress', 'in_progress', 'done'],
            $board->columns()->orderBy('position')->pluck('category')->map(fn($c) => $c->value)->all(),
        );
    }

    public function test_board_page_activates_and_renders(): void {
        $this->actingAs($this->lead)
            ->post(route('agile.activate', $this->project), ['method' => 'kanban'])
            ->assertRedirect(route('agile.board', $this->project));

        $this->actingAs($this->lead)
            ->get(route('agile.board', $this->project))
            ->assertOk()
            ->assertSee('Bereit')
            ->assertSee('Erledigt');
    }

    public function test_foreign_project_is_not_accessible(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignProject = Project::factory()->create(['organization_id' => $foreignOrg->id]);

        $this->actingAs($this->lead)
            ->get(route('agile.board', $foreignProject))
            ->assertNotFound(); // OrganizationScope: fremde Projekte existieren nicht
    }

    public function test_settings_conflict_yields_409(): void {
        $service = app(AgileBoardService::class);
        $board = $service->activate($this->project, AgileBoard::METHOD_KANBAN, $this->lead);

        // Konkurrierendes Update erhöht die lock_version.
        $service->updateSettings($board, ['name' => 'Zuerst'], 1, $this->lead);

        try {
            $service->updateSettings($board->fresh(), ['name' => 'Veraltet'], 1, $this->lead);
            $this->fail('Konflikt wurde nicht erkannt.');
        } catch (AgileConflictException) {
            // erwartet
        }

        // HTTP-Pfad: veraltete lock_version → 409.
        $this->actingAs($this->lead)
            ->patch(route('agile.settings.update', $this->project), [
                'name' => 'Neu',
                'method' => 'kanban',
                'lock_version' => 1,
            ])->assertStatus(409);

        $this->assertSame('Zuerst', $board->fresh()->name);
    }
}
