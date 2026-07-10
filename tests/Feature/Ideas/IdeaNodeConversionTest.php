<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeConversionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Ideas;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\{Customer, IdeaMap, IdeaNode, KnowledgeArticle, Project, Task, User};
use App\Services\Ideas\{IdeaMapService, IdeaNodeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 054, MVP-109: Überführung beschlossener Knoten in Aufgabe/Projekt/
 * Wissensartikel (idempotent, gegatet, berechtigungsgeprüft), Referenzen auf
 * bestehende Ziele und Sichtbarkeitsregel am verknüpften Projekt.
 */
final class IdeaNodeConversionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private IdeaMapService $maps;
    private User $owner;
    private IdeaMap $map;
    private IdeaNode $node;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->maps = app(IdeaMapService::class);
        // Admin statt „user": Überführung braucht die Ziel-Policies (Task/Projekt/Wissen).
        $this->owner = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->map = $this->maps->create($this->organization, $this->owner, 'Konvertier-Karte');
        $root = $this->map->rootNode()->firstOrFail();
        $this->node = app(IdeaNodeService::class)->create($this->map, $root, 'Beschlossene Idee', $this->owner);
    }

    public function test_convert_to_task_creates_kanban_task(): void {
        $this->actingAs($this->owner)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), [
            'target' => 'task',
        ])->assertCreated()->assertJsonPath('existing', false);

        $task = Task::query()->firstOrFail();
        $this->assertSame('Beschlossene Idee', $task->title);
        $this->assertTrue((bool) $task->is_global);
        $this->assertSame('Beschlossene Idee', $this->node->fresh()->title, 'Ausgangsknoten bleibt unverändert');
    }

    public function test_convert_is_idempotent_per_target_type(): void {
        $this->actingAs($this->owner)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), ['target' => 'task'])->assertCreated();
        $this->actingAs($this->owner)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), ['target' => 'task'])
            ->assertOk()->assertJsonPath('existing', true);

        $this->assertSame(1, Task::query()->count());
    }

    public function test_convert_to_project_inherits_map_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->map->forceFill(['customer_id' => $customer->id])->save();

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), [
            'target' => 'project',
        ])->assertCreated();

        $project = Project::query()->where('name', 'Beschlossene Idee')->firstOrFail();
        $this->assertSame($customer->id, (int) $project->customer_id);
    }

    public function test_convert_to_knowledge_creates_draft(): void {
        $this->actingAs($this->owner)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), [
            'target' => 'knowledge',
        ])->assertCreated();

        $article = KnowledgeArticle::query()->firstOrFail();
        $this->assertSame('Beschlossene Idee', $article->title);
        $this->assertSame('draft', $article->status->value);
    }

    public function test_convert_requires_enabled_target_module(): void {
        // Modul-Katalog des Kanban-Moduls org-seitig deaktivieren (MVP-052-Mechanik
        // ist separat getestet) — hier genügt der harte Lizenz-Weg: Free-Org.
        config()->set('plans.tiers.pro', array_values(array_diff(config('plans.tiers.pro'), ['module.kanban'])));
        config()->set('plans.tiers.enterprise', array_values(array_diff(config('plans.tiers.enterprise'), ['module.kanban'])));

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), [
            'target' => 'task',
        ])->assertStatus(422);

        $this->assertSame(0, Task::query()->count());
    }

    public function test_viewer_cannot_convert(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->maps->shareWithUser($this->map, $colleague, IdeaShareRole::Viewer, $this->owner);

        $this->actingAs($colleague)->postJson(route('ideas.nodes.convert', [$this->map, $this->node]), [
            'target' => 'task',
        ])->assertForbidden();
    }

    public function test_link_to_existing_customer_and_cross_org_guard(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->owner)->postJson(route('ideas.nodes.link', [$this->map, $this->node]), [
            'type' => 'customer', 'id' => $customer->sqid,
        ])->assertCreated()->assertJsonPath('reference.kind', 'linked');

        // Fremd-Org-Ziel wird nicht verknüpft (Scope blendet aus → 404).
        $orgB = \App\Models\Organization::factory()->create();
        $foreign = Customer::factory()->create(['organization_id' => $orgB->id]);
        $this->actingAs($this->owner)->postJson(route('ideas.nodes.link', [$this->map, $this->node]), [
            'type' => 'customer', 'id' => $foreign->sqid,
        ])->assertNotFound();
    }

    public function test_private_map_stays_hidden_on_linked_project_page(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->map->forceFill(['project_id' => $project->id])->save();

        $colleague = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Eigentümer sieht die Karte am Projekt …
        $this->actingAs($this->owner)->get(route('projects.show', $project))
            ->assertOk()->assertSee('Konvertier-Karte');

        // … ein anderer (selbst Admin) nicht — Sichtbarkeit wird nie vererbt.
        $this->actingAs($colleague)->get(route('projects.show', $project))
            ->assertOk()->assertDontSee('Konvertier-Karte');
    }
}
