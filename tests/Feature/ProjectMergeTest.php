<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMergeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, DiaryEntry, ExternalReference, Project, ProjectMergeDismissal, TimeEntry, User};
use App\Plugins\Toggl\Sources\TogglEntry;
use App\Plugins\Toggl\{TogglImportService, TogglPlugin};
use App\Services\{ProjectDuplicateFinder, ProjectMergeService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ProjectMergeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** Projekt ohne Kunde (vermeidet das vom CustomerObserver erzeugte Standardprojekt). */
    private function project(string $name, array $attributes = []): Project {
        return Project::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => null,
            'name' => $name,
        ], $attributes));
    }

    private function projectRef(Project $project, string $type, string $externalId, string $plugin = TogglPlugin::ID): ExternalReference {
        return ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $plugin,
            'external_type' => $type,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => $externalId,
        ]);
    }

    public function test_merge_repoints_relations_and_deletes_source(): void {
        $target = $this->project('Wartung');
        $source = $this->project('Wartung', ['description' => 'Sammelprojekt']);

        $time = TimeEntry::factory()->create([
            'project_id' => $source->id,
            'user_id' => $this->admin->id,
        ]);
        $diary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $source->id,
            'user_id' => $this->admin->id,
        ]);
        $ref = $this->projectRef($source, 'project', 'client x|wartung');

        app(ProjectMergeService::class)->merge($source, $target);

        $this->assertNull(Project::query()->find($source->id), 'Quelle wurde gelöscht');
        $this->assertSame($target->id, $time->fresh()->project_id);
        $this->assertSame($target->id, $diary->fresh()->project_id);
        $this->assertSame($target->id, $ref->fresh()->referenceable_id);
        // Leeres Ziel-Feld wird aus der Quelle aufgefüllt.
        $this->assertSame('Sammelprojekt', $target->fresh()->description);
    }

    public function test_merge_reparents_child_projects(): void {
        $target = $this->project('Wartung');
        $source = $this->project('Wartung');
        $child = $this->project('Wartung Detail', ['parent_id' => $source->id]);

        app(ProjectMergeService::class)->merge($source, $target);

        $this->assertSame($target->id, $child->fresh()->parent_id);
    }

    public function test_merge_handles_external_reference_collision(): void {
        $target = $this->project('Wartung');
        $source = $this->project('Wartung');

        // Beide tragen eine Toggl-Projekt-Referenz (gleiches Plugin+Typ) → Kollision.
        $this->projectRef($target, 'project', 'client|wartung');
        $sourceRef = $this->projectRef($source, 'project', 'client|wartung alt');

        app(ProjectMergeService::class)->merge($source, $target);

        $this->assertNull(ExternalReference::query()->find($sourceRef->id), 'Kollidierende Quell-Ref verworfen');
        $this->assertSame(1, ExternalReference::query()
            ->where('referenceable_type', $target->getMorphClass())
            ->where('referenceable_id', $target->id)
            ->where('external_type', 'project')->count());
    }

    public function test_merge_promotes_default_flag(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $target = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt A',
            'is_default' => false,
        ]);
        $source = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt A alt',
            'is_default' => true,
        ]);

        app(ProjectMergeService::class)->merge($source, $target);

        $this->assertTrue($target->fresh()->is_default, 'Standard-Flag wandert auf das Ziel');
    }

    public function test_merge_rejects_different_customers(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $target = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customerA->id, 'name' => 'X']);
        $source = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customerB->id, 'name' => 'X']);

        $this->expectException(\InvalidArgumentException::class);
        app(ProjectMergeService::class)->merge($source, $target);
    }

    public function test_finder_detects_same_customer_same_name(): void {
        $a = $this->project('Wartung');
        $b = $this->project('Wartung');

        $candidates = app(ProjectDuplicateFinder::class)->candidates($this->organization, ProjectDuplicateFinder::CONF_LIKELY);

        $pair = $candidates->first(fn(array $p): bool => in_array($p['target']->id, [$a->id, $b->id], true));
        $this->assertNotNull($pair, 'Gleichnamige Projekte desselben Kunden werden erkannt');
        $this->assertSame(ProjectDuplicateFinder::CONF_LIKELY, $pair['confidence']);
        $this->assertContains('name', $pair['reasons']);
    }

    public function test_finder_ignores_cross_customer_duplicates(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customerA->id, 'name' => 'Spezial']);
        Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customerB->id, 'name' => 'Spezial']);

        $candidates = app(ProjectDuplicateFinder::class)->candidates($this->organization);

        $crossPair = $candidates->first(fn(array $p): bool => $p['target']->name === 'Spezial' && $p['source']->name === 'Spezial');
        $this->assertNull($crossPair, 'Gleichnamige Projekte verschiedener Kunden werden NICHT gepaart');
    }

    public function test_finder_skips_parent_child_pairs(): void {
        $parent = $this->project('Wartung');
        $this->project('Wartung', ['parent_id' => $parent->id]);

        $candidates = app(ProjectDuplicateFinder::class)->candidates($this->organization);

        $this->assertCount(0, $candidates, 'Eltern/Kind-Paare werden nicht vorgeschlagen');
    }

    public function test_command_auto_merges_likely_pairs(): void {
        $this->project('Wartung');
        $this->project('Wartung');

        $this->artisan('project:merge-duplicates', ['--organization' => $this->organization->id, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(1, Project::query()->where('name', 'Wartung')->count());
    }

    public function test_dismiss_excludes_pair_from_finder(): void {
        $a = $this->project('Wartung');
        $b = $this->project('Wartung');

        $finder = app(ProjectDuplicateFinder::class);
        $this->assertCount(1, $finder->candidates($this->organization));

        ProjectMergeDismissal::query()->create(array_merge(
            ProjectMergeDismissal::pairKey($a->id, $b->id),
            ['organization_id' => $this->organization->id],
        ));

        $this->assertCount(0, $finder->candidates($this->organization));
    }

    public function test_merge_endpoint_requires_billing_permission(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->assignRole(\App\Enums\User\UserRole::Callcenter->value);
        $target = $this->project('Wartung');
        $source = $this->project('Wartung');

        $this->actingAs($user)
            ->post(route('projects.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertForbidden();

        $this->assertNotNull(Project::query()->find($source->id));
    }

    public function test_merge_endpoint_merges_for_admin(): void {
        $target = $this->project('Wartung');
        $source = $this->project('Wartung');

        $this->actingAs($this->admin)
            ->post(route('projects.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertRedirect(route('projects.duplicates.index'));

        $this->assertNull(Project::query()->find($source->id));
    }

    public function test_endpoint_surfaces_validation_error_for_cross_customer(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $target = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customerA->id, 'name' => 'X']);
        $source = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customerB->id, 'name' => 'X']);

        $this->actingAs($this->admin)
            ->from(route('projects.duplicates.index'))
            ->post(route('projects.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertRedirect(route('projects.duplicates.index'))
            ->assertSessionHasErrors('source');

        $this->assertNotNull(Project::query()->find($source->id), 'Kein Merge bei unterschiedlichen Kunden');
    }

    public function test_reimport_routes_to_merge_target(): void {
        $target = $this->project('Wartung');
        $source = $this->project('Wartung');
        $this->projectRef($source, 'project', 'client x|wartung');

        app(ProjectMergeService::class)->merge($source, $target);

        // Nach dem Merge zeigt die Toggl-Projekt-Referenz auf das Ziel — ein
        // erneuter Import desselben Client/Projekt-Namens ordnet sich automatisch zu.
        $entry = new TogglEntry(
            source: 'csv',
            entryKey: 'csv:test',
            clientName: 'Client X',
            projectName: 'Wartung',
            description: null,
            startedAt: CarbonImmutable::parse('2026-06-01 08:00:00'),
            endedAt: CarbonImmutable::parse('2026-06-01 09:00:00'),
        );

        $matched = app(TogglImportService::class)->matchProject($this->organization, $entry);

        $this->assertNotNull($matched);
        $this->assertSame($target->id, $matched->id);
    }
}
