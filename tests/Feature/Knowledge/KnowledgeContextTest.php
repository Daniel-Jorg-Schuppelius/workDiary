<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeContextTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Models\{DiaryEntry, KnowledgeArticle, User};
use App\Services\Knowledge\KnowledgeArticleService;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Problemhistorie (Feature 011): Verknüpfen/Lösen am Auftrag und
 * einfache Kontext-Vorschläge (LIKE-/Tag-Scoring, keine Volltext-Engine).
 */
class KnowledgeContextTest extends TestCase {
    use RefreshDatabase;

    public function test_article_can_be_linked_and_unlinked_on_diary_entry(): void {
        $user = User::factory()->user()->create();
        $entry = $this->makeDiaryEntryFor($user, 'Drucker streikt im EG');
        $article = $this->makeArticleFor($user, 'Drucker meldet Papierstau');

        $this->actingAs($user)
            ->post(route('knowledge.links.store', $article), [
                'subject_kind' => 'diary',
                'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_article_links', [
            'knowledge_article_id' => $article->id,
            'linkable_type' => DiaryEntry::class,
            'linkable_id' => $entry->id,
            'created_by_user_id' => $user->id,
        ]);

        // Doppeltes Verknüpfen bleibt idempotent (Unique-Index).
        $this->actingAs($user)
            ->post(route('knowledge.links.store', $article), [
                'subject_kind' => 'diary',
                'subject_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            ])
            ->assertRedirect();

        app()->instance('currentOrganization', $user->organization);
        $this->assertSame(1, $article->links()->count());

        $link = $article->links()->firstOrFail();
        $this->actingAs($user)
            ->delete(route('knowledge.links.destroy', [$article, $link]))
            ->assertRedirect();

        $this->assertDatabaseMissing('knowledge_article_links', ['id' => $link->id]);
    }

    public function test_linking_cross_org_subject_is_not_found(): void {
        $user = User::factory()->user()->create();
        $foreignUser = User::factory()->user()->create(); // eigene Organisation
        $foreignEntry = $this->makeDiaryEntryFor($foreignUser, 'Fremder Auftrag');
        $article = $this->makeArticleFor($user, 'Eigener Artikel');

        $this->actingAs($user)
            ->post(route('knowledge.links.store', $article), [
                'subject_kind' => 'diary',
                'subject_id' => Sqid::encode(DiaryEntry::class, $foreignEntry->id),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('knowledge_article_links', 0);
    }

    public function test_suggestion_query_returns_article_matching_words_from_entry(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        $entry = $this->makeDiaryEntryFor($user, 'Papierstau am Drucker im Erdgeschoss');

        $match = KnowledgeArticle::factory()->published()->create([
            'title' => 'Drucker meldet Papierstau trotz leerem Einzug',
            'problem' => 'Modell X zeigt dauerhaft Papierstau.',
            'created_by_user_id' => $user->id,
        ]);
        // Entwurf wird nie vorgeschlagen, auch wenn der Titel passt.
        KnowledgeArticle::factory()->create([
            'title' => 'Papierstau Entwurf',
            'created_by_user_id' => $user->id,
        ]);
        // Themenfremder Artikel taucht nicht auf.
        KnowledgeArticle::factory()->published()->create([
            'title' => 'VPN bricht ab',
            'problem' => 'MTU-Problem',
            'created_by_user_id' => $user->id,
        ]);

        $suggestions = app(KnowledgeArticleService::class)
            ->suggestFor($entry, [(string) $entry->title, (string) $entry->content]);

        $this->assertSame([$match->id], $suggestions->pluck('id')->all());
    }

    public function test_suggestion_query_matches_shared_tags_and_skips_already_linked(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);
        $this->actingAs($user);

        $entry = $this->makeDiaryEntryFor($user, 'Routinewartung');
        $entry->syncTagsFromInput([], ['heizung']);

        $tagMatch = KnowledgeArticle::factory()->published()->create([
            'title' => 'Brennerstörung quittieren',
            'problem' => 'Störcode F28 nach Stromausfall.',
            'created_by_user_id' => $user->id,
        ]);
        $tagMatch->syncTagsFromInput([], ['heizung']);

        $linked = KnowledgeArticle::factory()->published()->create([
            'title' => 'Routinewartung Checkliste',
            'problem' => 'Schritte zur Routinewartung.',
            'created_by_user_id' => $user->id,
        ]);

        $service = app(KnowledgeArticleService::class);
        $service->linkTo($linked, $entry, $user);

        $suggestions = $service->suggestFor($entry, [(string) $entry->title, (string) $entry->content]);

        $this->assertContains($tagMatch->id, $suggestions->pluck('id')->all());
        // Bereits verknüpfte Artikel sind keine Vorschläge mehr.
        $this->assertNotContains($linked->id, $suggestions->pluck('id')->all());
    }

    public function test_diary_show_page_renders_knowledge_card_with_suggestion(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        $entry = $this->makeDiaryEntryFor($user, 'Papierstau am Drucker im Erdgeschoss');
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Drucker meldet Papierstau trotz leerem Einzug',
            'problem' => 'Modell X zeigt dauerhaft Papierstau.',
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee(__('knowledge.title.suggestions'))
            ->assertSee('Drucker meldet Papierstau trotz leerem Einzug');
    }

    public function test_create_dialog_prefills_problem_from_diary_entry(): void {
        $user = User::factory()->user()->create();
        $entry = $this->makeDiaryEntryFor($user, 'Papierstau am Drucker');

        $this->actingAs($user)
            ->get(route('knowledge.create', [
                'source_kind' => 'diary',
                'source_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            ]))
            ->assertOk()
            ->assertSee('Papierstau am Drucker');
    }

    public function test_store_with_link_creates_article_and_link(): void {
        $user = User::factory()->user()->create();
        $entry = $this->makeDiaryEntryFor($user, 'Papierstau am Drucker');

        $this->actingAs($user)
            ->post(route('knowledge.store'), [
                'title' => 'Papierstau beheben',
                'problem' => 'Papierstau am Drucker',
                'solution' => 'Einzugsrolle reinigen.',
                'link_kind' => 'diary',
                'link_id' => Sqid::encode(DiaryEntry::class, $entry->id),
            ])
            ->assertRedirect();

        app()->instance('currentOrganization', $user->organization);
        $article = KnowledgeArticle::query()->firstOrFail();
        $this->assertDatabaseHas('knowledge_article_links', [
            'knowledge_article_id' => $article->id,
            'linkable_type' => DiaryEntry::class,
            'linkable_id' => $entry->id,
        ]);
    }

    private function makeDiaryEntryFor(User $user, string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }

    private function makeArticleFor(User $creator, string $title): KnowledgeArticle {
        return KnowledgeArticle::factory()->published()->create([
            'organization_id' => $creator->organization_id,
            'created_by_user_id' => $creator->id,
            'title' => $title,
        ]);
    }
}
