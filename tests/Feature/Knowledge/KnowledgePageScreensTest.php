<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageScreensTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\User\Permission;
use App\Models\Diary\DiaryEntry;
use App\Models\Knowledge\{KnowledgeGlossaryTerm, KnowledgePage, KnowledgeSection, KnowledgeSpace};
use App\Models\Platform\{Team, User};
use App\Services\Knowledge\Pages\{KnowledgePageLinks, KnowledgePageService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Oberflächen der Wissensdatenbank (Feature 191): Dialoge und Seiten öffnen,
 * Bereiche mit Lesern und Rubriken pflegen, Glossar, Rechte der Verwaltung,
 * Karte „Wissensseiten“ am Auftrag.
 */
final class KnowledgePageScreensTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_spaces_sections_and_readers_are_managed(): void {
        $team = Team::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Leitungskreis']);

        $this->actingAs($this->admin)->get(route('knowledge.spaces.create'))->assertOk()->assertSee('Leitungskreis');
        $this->actingAs($this->admin)->post(route('knowledge.spaces.store'), [
            'title' => 'Leitung',
            'is_restricted' => '1',
            'teams' => [\App\Support\Sqid::encode(Team::class, (int) $team->id)],
        ])->assertSessionHasNoErrors();

        $space = KnowledgeSpace::query()->where('title', 'Leitung')->firstOrFail();
        $this->assertTrue($space->is_restricted);
        $this->assertSame(1, $space->readers()->where('kind', 'team')->where('team_id', $team->id)->count());

        $this->actingAs($this->admin)->get(route('knowledge.spaces.edit', $space))->assertOk();
        $this->actingAs($this->admin)->get(route('knowledge.sections.create', $space))->assertOk();
        $this->actingAs($this->admin)->post(route('knowledge.sections.store', $space), ['title' => 'Protokolle'])->assertSessionHasNoErrors();
        $section = KnowledgeSection::query()->where('title', 'Protokolle')->firstOrFail();
        $this->actingAs($this->admin)->get(route('knowledge.sections.edit', $section))->assertOk();
        $this->actingAs($this->admin)->get(route('knowledge.pages.index', ['space' => $space->sqid, 'section' => $section->sqid]))->assertOk()->assertSee('Protokolle');

        // Ein Bereich mit Seiten lässt sich nicht löschen.
        app(KnowledgePageService::class)->create($space, $section, null, $this->admin, ['title' => 'Sitzung März']);
        $this->actingAs($this->admin)->delete(route('knowledge.spaces.destroy', $space))->assertSessionHas('error');
        $this->assertDatabaseHas('knowledge_spaces', ['id' => $space->id]);

        // Rubrik löschen: die Seite bleibt im Bereich.
        $this->actingAs($this->admin)->delete(route('knowledge.sections.destroy', $section))->assertRedirect();
        $this->assertNull(KnowledgePage::query()->where('title', 'Sitzung März')->value('knowledge_section_id'));

        $editor = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny, Permission::KnowledgePageEdit]);
        $this->actingAs($editor)->get(route('knowledge.spaces.create'))->assertForbidden();
    }

    public function test_dialogs_and_lists_render(): void {
        $space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(KnowledgePageService::class);
        $page = $service->approve($service->create($space, null, null, $this->admin, ['title' => 'Bildschirm sperren', 'body' => 'Windows + L']), $this->admin);
        $guide = $service->create($space, null, null, $this->admin, ['title' => 'Drucker einrichten', 'kind' => 'guide']);

        foreach ([
            route('knowledge.pages.index'),
            route('knowledge.pages.create', ['space' => $space->sqid]),
            route('knowledge.pages.create', ['parent' => $page->sqid]),
            route('knowledge.pages.edit', $page),
            route('knowledge.pages.responsibility', $page),
            route('knowledge.pages.readings', $page),
            route('knowledge.pages.links.create', $page),
            route('knowledge.pages.import'),
            route('knowledge.pages.trash'),
            route('knowledge.readings.index'),
            route('knowledge.glossary.index'),
            route('knowledge.glossary.create', ['term' => 'ust-va']),
            route('knowledge.gaps.index'),
            route('knowledge.pages.steps.create', $guide),
            route('knowledge.pages.show', $guide),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_responsibility_moves_the_page_and_sets_dates(): void {
        $space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(KnowledgePageService::class);
        $parent = $service->create($space, null, null, $this->admin, ['title' => 'Oben']);
        $page = $service->create($space, null, null, $this->admin, ['title' => 'Unten']);
        $owner = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);

        $this->actingAs($this->admin)->put(route('knowledge.pages.responsibility.update', $page), [
            'owner' => \App\Support\Sqid::encode(User::class, (int) $owner->id),
            'review_interval_months' => 12,
            'valid_until' => now()->addYear()->toDateString(),
            'is_emergency' => '1',
            'space' => $space->sqid,
            'parent' => $parent->sqid,
        ])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame((int) $owner->id, (int) $page->owner_user_id);
        $this->assertSame((int) $parent->id, (int) $page->parent_id);
        $this->assertTrue($page->is_emergency);
        $this->assertNotNull($page->next_review_on);

        // Kein Kreis: die obere Seite kann nicht unter ihre Unterseite.
        $this->actingAs($this->admin)->put(route('knowledge.pages.responsibility.update', $parent), [
            'space' => $space->sqid,
            'parent' => $page->sqid,
        ])->assertSessionHasErrors('parent');
    }

    public function test_glossary_is_maintained_by_management_only(): void {
        $this->actingAs($this->admin)->post(route('knowledge.glossary.store'), [
            'term' => 'Arbeitsvorrat',
            'synonyms' => "AV\nTo-do-Liste",
        ])->assertSessionHasNoErrors();
        $term = KnowledgeGlossaryTerm::query()->where('term', 'Arbeitsvorrat')->firstOrFail();
        $this->assertSame(['AV', 'To-do-Liste'], $term->synonyms);

        $this->actingAs($this->admin)->post(route('knowledge.glossary.store'), ['term' => 'arbeitsvorrat'])->assertSessionHasErrors('term');

        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $this->actingAs($reader)->get(route('knowledge.glossary.index'))->assertOk()->assertSee('Arbeitsvorrat');
        $this->actingAs($reader)->post(route('knowledge.glossary.store'), ['term' => 'Neu'])->assertForbidden();
    }

    public function test_order_card_lists_linked_pages_and_links_from_the_order(): void {
        $space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(KnowledgePageService::class);
        $page = $service->approve($service->create($space, null, null, $this->admin, ['title' => 'Wartung Heizung']), $this->admin);
        $order = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin);
        $html = view('knowledge-pages._linked_card', ['subject' => $order])->render();
        $this->assertStringContainsString('Wartung Heizung', $html, 'Die Auswahl bietet die Seite an.');

        $this->actingAs($this->admin)->post(route('knowledge.pages.links.store'), [
            'page' => $page->sqid,
            'target' => 'order:' . \App\Support\Sqid::encode(DiaryEntry::class, (int) $order->id),
        ])->assertSessionHasNoErrors();
        $this->assertCount(1, app(KnowledgePageLinks::class)->pagesFor($order, $this->admin));
    }
}
