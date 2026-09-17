<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchFacetsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\User\Permission;
use App\Models\{CommunicationNote, ContentCollection, DiaryEntry, Tag, User};
use App\Services\Collections\ContentCollectionService;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Knowledge\KnowledgeArticleService;
use App\Services\Search\{ActivitySearchCriteria, ActivitySearchResult, ActivitySearchService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Sammlung und Schlagwort als Facetten der Recherche (MVP-812, Feature 155).
 */
final class SearchFacetsTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        config(['search.indexing' => true]);
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_tag_filter_narrows_across_sources_and_works_without_a_term(): void {
        $this->note($this->admin, 'Heizung Halle B', 'Wartung');
        $this->note($this->admin, 'Heizung Halle C');
        app(KnowledgeArticleService::class)->create($this->admin, ['title' => 'Heizung entlüften', 'problem' => 'Luft im Heizkreis', 'solution' => 'Entlüften.', 'tags' => 'Wartung']);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'title' => 'Heizung Kundendienst'])->syncTagNames('Wartung');

        $wartung = Tag::query()->where('name', 'Wartung')->firstOrFail();

        $withTerm = $this->search($this->admin, 'heizung', ['tagId' => $wartung->id]);
        $this->assertEqualsCanonicalizing(['Heizung Halle B', 'Heizung entlüften', 'Heizung Kundendienst'], $this->titles($withTerm));

        // Ohne Suchwort genügt das Schlagwort als Bezug.
        $this->assertSame(3, $this->search($this->admin, '', ['tagId' => $wartung->id])->hits->total());
    }

    public function test_tag_facets_count_the_hits_and_hide_tags_of_hidden_documents(): void {
        $author = $this->member([Permission::CommunicationConfidentialManage]);
        $this->note($author, 'Rückruf Abmahnung', 'Disziplinarfall', confidential: true);
        $this->note($author, 'Rückruf Lieferant', 'Einkauf');
        $this->note($author, 'Rückruf Monteur', 'Einkauf');

        $facets = $this->search($author, 'rückruf')->tagFacets;
        $this->assertSame([['Einkauf', 2], ['Disziplinarfall', 1]], array_map(static fn (array $f): array => [$f['name'], $f['hits']], $facets));

        $colleague = $this->member();
        $this->assertSame(['Einkauf'], array_column($this->search($colleague, 'rückruf')->tagFacets, 'name'));

        // Auch über die URL verrät das Schlagwort-Badge den Namen nicht.
        $hidden = Tag::query()->where('name', 'Disziplinarfall')->firstOrFail();
        $this->actingAs($colleague)->get(route('search.index', ['q' => 'rückruf', 'tag' => $hidden->sqid]))
            ->assertOk()
            ->assertDontSee('Disziplinarfall')
            ->assertSee(__('search.filter.tag_without_hits'));
    }

    public function test_collection_filter_includes_sub_collections_and_respects_privacy(): void {
        $service = app(ContentCollectionService::class);
        $parent = $service->create($this->organization, $this->admin, ['title' => 'Kunde Müller']);
        $child = $service->create($this->organization, $this->admin, ['title' => 'Gewährleistung', 'parent_id' => $parent->id]);
        $inParent = $this->note($this->admin, 'Angebot Müller Dach');
        $inChild = $this->note($this->admin, 'Mängelrüge Müller Dach');
        $this->note($this->admin, 'Dach Nachbar');
        $service->addItem($parent, $this->admin, $inParent);
        $service->addItem($child, $this->admin, $inChild);

        $this->assertEqualsCanonicalizing(['Angebot Müller Dach', 'Mängelrüge Müller Dach'], $this->titles($this->search($this->admin, 'dach', ['collectionId' => $parent->id])));
        $this->assertSame(['Mängelrüge Müller Dach'], $this->titles($this->search($this->admin, '', ['collectionId' => $child->id])));

        // Eine fremde private Sammlung filtert auf nichts statt auf alles.
        $owner = $this->member();
        $private = $service->create($this->organization, $owner, ['title' => 'Merkliste', 'visibility' => ContentCollection::VISIBILITY_PRIVATE]);
        $this->assertSame(0, $this->search($this->member(), 'dach', ['collectionId' => $private->id])->hits->total());
    }

    public function test_search_page_offers_collections_and_tag_facets(): void {
        app(ContentCollectionService::class)->create($this->organization, $this->admin, ['title' => 'Einarbeitung']);
        $this->note($this->admin, 'Checkliste erster Tag', 'Onboarding');

        $response = $this->actingAs($this->admin)->get(route('search.index', ['q' => 'checkliste']))
            ->assertOk()
            ->assertSee('name="collection"', false)
            ->assertSee('Einarbeitung')
            ->assertSee(__('search.facets.tags'))
            ->assertSee('Onboarding');

        $tag = Tag::query()->where('name', 'Onboarding')->firstOrFail();
        $response->assertSee(e(route('search.index', ['q' => 'checkliste', 'tag' => $tag->sqid])), false);

        $this->actingAs($this->admin)->get(route('search.index', ['q' => 'checkliste', 'tag' => $tag->sqid]))
            ->assertOk()
            ->assertSee(__('search.filter.tag', ['name' => 'Onboarding']));
    }

    public function test_without_collection_rights_the_filter_is_neither_offered_nor_applied(): void {
        $collection = app(ContentCollectionService::class)->create($this->organization, $this->admin, ['title' => 'Team']);
        $note = $this->note($this->admin, 'Teamtermin Planung');
        app(ContentCollectionService::class)->addItem($collection, $this->admin, $note);

        $reader = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($reader, [Permission::CommunicationViewAny, Permission::CommunicationView]);

        $this->assertSame(0, $this->search($reader, 'teamtermin', ['collectionId' => $collection->id])->hits->total());
        $this->actingAs($reader)->get(route('search.index', ['q' => 'teamtermin']))
            ->assertOk()
            ->assertDontSee('name="collection"', false);
    }

    /** @param array<string, mixed> $criteria */
    private function search(User $user, string $query, array $criteria = []): ActivitySearchResult {
        $this->actAsTeam($user->organization_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return app(ActivitySearchService::class)->search($user, new ActivitySearchCriteria(...array_merge(['query' => $query], $criteria)));
    }

    /** @return list<string> */
    private function titles(ActivitySearchResult $result): array {
        return array_values(array_map(static fn ($hit): string => $hit->title, $result->hits->items()));
    }

    private function note(User $actor, string $subject, string $tags = '', bool $confidential = false): CommunicationNote {
        return app(CommunicationNoteService::class)->create($this->organization, $actor, [
            'type' => CommunicationNoteType::General->value,
            'direction' => CommunicationDirection::Internal->value,
            'occurred_at' => now()->subHour()->toDateTimeString(),
            'subject' => $subject,
            'body' => 'Inhalt.',
            'confidential' => $confidential,
            'tags' => $tags,
        ]);
    }

    /** @param list<Permission> $extra */
    private function member(array $extra = []): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($user, [
            Permission::CollectionViewAny,
            Permission::CollectionManage,
            Permission::CommunicationViewAny,
            Permission::CommunicationView,
            Permission::CommunicationCreate,
            ...$extra,
        ]);

        return $user;
    }
}
