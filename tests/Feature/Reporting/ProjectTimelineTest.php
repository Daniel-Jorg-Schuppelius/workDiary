<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimelineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{CommunicationNote, Customer, DiaryEntry, Document, Project, User};
use App\Services\Timeline\ProjectTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 56: Projekt-Timeline — Quellen-Aggregation, Sichtbarkeitsfilter
 * (vertrauliche Notizen), Pagination-Kappe.
 */
class ProjectTimelineTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_timeline_aggregates_sources_and_respects_confidentiality(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $author = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'is_default' => false,
        ]);

        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $author->id,
            'title' => 'Kesselwartung März',
        ]);
        Document::factory()->create([
            'organization_id' => $this->organization->id,
            'documentable_type' => $project->getMorphClass(),
            'documentable_id' => $project->id,
            'created_by_user_id' => $author->id,
            'title' => 'Wartungsvertrag',
        ]);
        CommunicationNote::query()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => $project->getMorphClass(),
            'notable_id' => $project->id,
            'created_by_user_id' => $author->id,
            'type' => 'internal',
            'direction' => 'internal',
            'subject' => 'Geheimabsprache',
            'body' => 'intern',
            'confidential' => true,
            'occurred_at' => now(),
        ]);

        $result = app(ProjectTimelineService::class)->forProject($project, $viewer, 50);
        $titles = array_map(static fn ($item): string => $item->title, $result['items']);

        $this->assertContains('Kesselwartung März', $titles);
        $this->assertContains(__('Dokument: :title', ['title' => 'Wartungsvertrag']), $titles);
        // Vertrauliche Notiz eines anderen Autors bleibt unsichtbar.
        $this->assertNotContains('Geheimabsprache', $titles);

        // Für den Autor selbst sichtbar.
        $forAuthor = app(ProjectTimelineService::class)->forProject($project, $author, 50);
        $this->assertContains('Geheimabsprache', array_map(static fn ($item): string => $item->title, $forAuthor['items']));
    }

    public function test_timeline_paginates(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'is_default' => false,
        ]);
        DiaryEntry::factory()->count(12)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $viewer->id,
        ]);

        $page1 = app(ProjectTimelineService::class)->forProject($project, $viewer, 10);
        $this->assertCount(10, $page1['items']);
        $this->assertTrue($page1['hasMore']);

        $page2 = app(ProjectTimelineService::class)->forProject($project, $viewer, 10, 10);
        $this->assertFalse($page2['hasMore']);
    }

    public function test_project_page_renders_timeline_tab(): void {
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'is_default' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('Timeline'));
    }
}
