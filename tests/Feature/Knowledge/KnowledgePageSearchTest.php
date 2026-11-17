<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Knowledge\{ContentReference, KnowledgeGlossaryTerm, KnowledgePage, KnowledgeSearchGap, KnowledgeSpace};
use App\Models\Platform\User;
use App\Services\Collections\{CollectableTypes, ContentReferenceService};
use App\Services\Knowledge\Pages\Cockpit\KnowledgePageCockpitPanel;
use App\Services\Knowledge\Pages\{KnowledgePageLinks, KnowledgePageService};
use App\Services\Knowledge\Pages\Search\KnowledgePageSearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Finden und Verweisen (MVP-1667): Glossar in der Suche, Suchlücken ohne
 * Personenbezug, globale Suche, „geändert seit Ihrem letzten Besuch“,
 * Verknüpfung mit Fachobjekten samt Cockpit und Sammlungen.
 */
final class KnowledgePageSearchTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private KnowledgeSpace $space;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->space = KnowledgeSpace::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_glossary_synonyms_widen_the_search(): void {
        $page = $this->approvedPage('Umsatzsteuer-Voranmeldung abgeben', 'Monatlich bis zum Zehnten.');
        KnowledgeGlossaryTerm::query()->create([
            'organization_id' => $this->organization->id,
            'term' => 'Umsatzsteuer-Voranmeldung',
            'synonyms' => ['USt-VA'],
        ]);
        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);

        $this->actingAs($reader)->get(route('knowledge.pages.index', ['q' => 'USt-VA']))
            ->assertOk()
            ->assertSee('Umsatzsteuer-Voranmeldung abgeben');

        $hits = app(KnowledgePageSearchDomain::class)->search($reader, (int) $this->organization->id, 'USt-VA', new SearchDomainFilters(), 10);
        $this->assertSame([$page->id], array_column($hits, 'id'));
        $this->assertSame([], app(KnowledgePageSearchDomain::class)->search($reader, (int) $this->organization->id, 'USt-VA', new SearchDomainFilters(customer: 1), 10));
        $this->assertSame(0, KnowledgeSearchGap::query()->count());
    }

    public function test_search_gaps_are_counted_without_a_person(): void {
        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $this->actingAs($reader)->get(route('knowledge.pages.index', ['q' => 'Dienstwagen tanken']))->assertOk();
        $this->actingAs($this->admin)->get(route('knowledge.pages.index', ['q' => 'dienstwagen  TANKEN']))->assertOk();

        $gap = KnowledgeSearchGap::query()->firstOrFail();
        $this->assertSame('dienstwagen tanken', $gap->term);
        $this->assertSame(2, $gap->searches);
        $this->assertSame(['id', 'organization_id', 'term', 'searches', 'first_searched_at', 'last_searched_at', 'dismissed_at'], array_keys($gap->getAttributes()));

        $this->actingAs($reader)->get(route('knowledge.gaps.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('knowledge.gaps.index'))->assertOk()->assertSee('dienstwagen tanken');
        $this->actingAs($this->admin)->post(route('knowledge.gaps.dismiss', $gap))->assertSessionHasNoErrors();
        $this->assertNotNull($gap->refresh()->dismissed_at);
    }

    public function test_changed_since_last_visit_is_shown_on_list_and_page(): void {
        $page = $this->approvedPage('Telefonregeln', 'Fassung eins');
        $reader = $this->actorIn($this->organization, [Permission::KnowledgePageViewAny]);
        $this->actingAs($reader)->get(route('knowledge.pages.show', $page))->assertOk()->assertDontSee(__('knowledge_pages.badge.changed'));

        $service = app(KnowledgePageService::class);
        $service->update($page, $this->admin, ['title' => 'Telefonregeln', 'body' => 'Fassung zwei'], 1);
        $service->approve($page->refresh(), $this->admin);

        $this->actingAs($reader)->get(route('knowledge.pages.index', ['filter' => 'changed']))
            ->assertOk()
            ->assertSee('Telefonregeln')
            ->assertSee(__('knowledge_pages.badge.changed'));
        $this->actingAs($reader)->get(route('knowledge.pages.show', $page))
            ->assertOk()
            ->assertSee(__('knowledge_pages.banner.changed_since', ['seen' => 1, 'version' => 2]));
        // Gesehen ist gesehen.
        $this->actingAs($reader)->get(route('knowledge.pages.index', ['filter' => 'changed']))->assertOk()->assertSee(__('knowledge_pages.empty.pages'));
    }

    public function test_pages_link_to_customers_and_show_in_the_cockpit_but_not_twice(): void {
        $page = $this->approvedPage('Kunde Beispiel: Zugang', 'Schlüssel beim Hausmeister.');
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beispiel GmbH']);

        $this->actingAs($this->admin)->get(route('knowledge.pages.links.create', $page))->assertOk()->assertSee('Beispiel GmbH');
        $this->actingAs($this->admin)->post(route('knowledge.pages.links.store'), [
            'page' => $page->sqid,
            'target' => 'customer:' . $customer->sqid,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('content_references', ['source_id' => $page->id, 'target_id' => $customer->id, 'kind' => ContentReference::KIND_LINKED]);

        $view = app(KnowledgePageCockpitPanel::class)->render($customer, $this->admin);
        $this->assertNotNull($view);
        $this->assertStringContainsString('Kunde Beispiel: Zugang', $view->render());
        // Die Rückverweise zeigen die Fachverknüpfung nicht noch einmal.
        $this->assertSame([], app(ContentReferenceService::class)->backlinks($customer, $this->admin));

        $this->actingAs($this->admin)->get(route('knowledge.pages.show', $page))->assertOk()->assertSee('Beispiel GmbH');
        $this->assertCount(1, app(KnowledgePageLinks::class)->pagesFor($customer, $this->admin));
    }

    public function test_pages_are_collectable_and_appear_in_the_knowledge_hub(): void {
        $page = $this->approvedPage('Sammelbare Seite', 'Text');
        $types = app(CollectableTypes::class);
        $this->assertSame('knowledge_page', $types->keyFor($page));
        $this->assertContains('knowledge_page', $types->availableKeys($this->admin));

        $this->actingAs($this->admin)->get(route('knowledge-hub.index', ['type' => 'knowledge_page']))
            ->assertOk()
            ->assertSee('Sammelbare Seite');
    }

    private function approvedPage(string $title, string $body): KnowledgePage {
        $service = app(KnowledgePageService::class);

        return $service->approve($service->create($this->space, null, null, $this->admin, ['title' => $title, 'body' => $body]), $this->admin);
    }
}
