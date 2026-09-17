<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeHubTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission;
use App\Models\{AuditLog, CommunicationNote, ContentCollectionItem, ContentReference, IdeaMap, KnowledgeArticle, Tag, User};
use App\Services\Collections\ContentCollectionService;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Einstieg „Wissen“, Sammeln aus Listen und Treffern, Notiz → Wissensartikel
 * (MVP-813, Feature 155).
 */
final class KnowledgeHubTest extends TestCase {
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

    public function test_hub_lists_all_kinds_and_filters_by_title_type_tag_and_collection(): void {
        $note = $this->note($this->admin, 'Heizung Übergabe', 'Heizung');
        IdeaMap::factory()->create(['organization_id' => $this->organization->id, 'owner_user_id' => $this->admin->id, 'created_by' => $this->admin->id, 'title' => 'Ideen Heizung']);
        $article = $this->article('Heizung entlüften');
        $this->note($this->admin, 'Parkplatzregelung');

        $this->actingAs($this->admin)->get(route('knowledge-hub.index'))
            ->assertOk()
            ->assertSee('Heizung Übergabe')
            ->assertSee('Ideen Heizung')
            ->assertSee('Heizung entlüften')
            ->assertSee('Parkplatzregelung');

        $this->actingAs($this->admin)->get(route('knowledge-hub.index', ['q' => 'heizung', 'type' => 'note']))
            ->assertOk()
            ->assertSee('Heizung Übergabe')
            ->assertDontSee('Heizung entlüften')
            ->assertDontSee('Parkplatzregelung');

        $tag = Tag::query()->where('name', 'Heizung')->firstOrFail();
        $this->actingAs($this->admin)->get(route('knowledge-hub.index', ['tag' => $tag->sqid]))
            ->assertOk()
            ->assertSee('Heizung Übergabe')
            ->assertDontSee('Ideen Heizung');

        $service = app(ContentCollectionService::class);
        $parent = $service->create($this->organization, $this->admin, ['title' => 'Haustechnik']);
        $child = $service->create($this->organization, $this->admin, ['title' => 'Wärme', 'parent_id' => $parent->id]);
        $service->addItem($child, $this->admin, $article);
        $this->actingAs($this->admin)->get(route('knowledge-hub.index', ['collection' => $parent->sqid]))
            ->assertOk()
            ->assertSee('Heizung entlüften')
            ->assertDontSee('Heizung Übergabe');

        $this->assertNotNull($note);
    }

    public function test_hub_hides_what_the_fach_lists_hide(): void {
        $author = $this->member([Permission::CommunicationConfidentialManage]);
        $this->note($author, 'Abmahnung Entwurf', 'Disziplinarfall', confidential: true);
        $this->note($author, 'Teamfrühstück');

        $colleague = $this->member();
        $this->actingAs($colleague)->get(route('knowledge-hub.index'))
            ->assertOk()
            ->assertSee('Teamfrühstück')
            ->assertDontSee('Abmahnung Entwurf')
            ->assertDontSee('Disziplinarfall');

        // Ohne jeden sichtbaren Inhaltstyp gibt es keinen Einstieg.
        $nobody = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($nobody)->get(route('knowledge-hub.index'))->assertForbidden();
    }

    public function test_several_items_go_into_a_collection_at_once_and_hidden_ones_are_skipped(): void {
        $author = $this->member();
        $collection = app(ContentCollectionService::class)->create($this->organization, $author, ['title' => 'Messe 2027']);
        $first = $this->note($author, 'Standplanung');
        $second = $this->note($author, 'Hotelbuchung');
        $confidential = $this->note($this->admin, 'Gehaltsrunde', confidential: true);

        $this->actingAs($author)->post(route('collections.items.bulk'), [
            'collection' => $collection->sqid,
            'items' => ['note:' . $first->sqid, 'note:' . $second->sqid, 'note:' . $confidential->sqid, 'note:' . $first->sqid],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(2, ContentCollectionItem::query()->count());
    }

    public function test_search_hits_can_be_collected(): void {
        config(['search.indexing' => true]);
        app(ContentCollectionService::class)->create($this->organization, $this->admin, ['title' => 'Recherche Dach']);
        $note = $this->note($this->admin, 'Dachrinne undicht');

        $this->actingAs($this->admin)->get(route('search.index', ['q' => 'dachrinne']))
            ->assertOk()
            ->assertSee('data-bulk-checkbox', false)
            ->assertSee('note:' . $note->sqid, false)
            ->assertSee(e(route('collections.items.bulk')), false);
    }

    public function test_a_note_becomes_a_draft_article_that_points_back_to_it(): void {
        $note = $this->note($this->admin, 'Drucker Papierstau Lösung', 'Drucker');
        $note->forceFill(['body' => 'Einzugsrolle mit Alkohol reinigen.'])->saveQuietly();

        $response = $this->actingAs($this->admin)->post(route('communication-notes.convert-knowledge', $note));

        $article = KnowledgeArticle::query()->where('title', 'Drucker Papierstau Lösung')->firstOrFail();
        $response->assertRedirect(route('knowledge.show', $article));
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertSame('Einzugsrolle mit Alkohol reinigen.', $article->problem);
        $this->assertSame(['Drucker'], $article->tags->pluck('name')->all());
        $this->assertDatabaseHas('content_references', [
            'source_type' => $note->getMorphClass(),
            'source_id' => $note->id,
            'target_type' => $article->getMorphClass(),
            'target_id' => $article->id,
            'kind' => ContentReference::KIND_CONVERTED,
        ]);
        $this->assertTrue(AuditLog::query()->where('event', 'communication.converted')->exists());

        // Der Artikel trägt den Verweis auf die Notiz.
        $this->actingAs($this->admin)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertSee(__('collections.references.backlinks'))
            ->assertSee(__('collections.references.kind.converted'));

        // Zweiter Versuch: kein zweiter Artikel.
        $this->actingAs($this->admin)->post(route('communication-notes.convert-knowledge', $note))->assertRedirect(route('knowledge.show', $article));
        $this->assertSame(1, KnowledgeArticle::query()->count());
    }

    public function test_confidential_notes_and_missing_module_block_the_conversion(): void {
        $confidential = $this->note($this->admin, 'Kündigungsgespräch', confidential: true);
        $this->actingAs($this->admin)->post(route('communication-notes.convert-knowledge', $confidential))->assertSessionHas('error');

        $note = $this->note($this->admin, 'Notiz ohne Wissensmodul');
        config(['license.feature_overrides' => ['module.knowledge' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->actingAs($this->admin)->post(route('communication-notes.convert-knowledge', $note))->assertSessionHas('error');

        $this->assertSame(0, KnowledgeArticle::query()->count());
    }

    public function test_navigation_offers_the_knowledge_entry(): void {
        $this->actingAs($this->admin)->get(route('knowledge-hub.index'))
            ->assertOk()
            ->assertSee(route('knowledge-hub.index'), false);
    }

    private function article(string $title): KnowledgeArticle {
        return KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => $title,
            'status' => ArticleStatus::Published->value,
            'created_by_user_id' => $this->admin->id,
        ]);
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
