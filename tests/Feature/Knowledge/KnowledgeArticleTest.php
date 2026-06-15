<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\Knowledge\ArticleStatus;
use App\Models\{KnowledgeArticle, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeArticleTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_create_article_as_draft_with_tags(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->from(route('knowledge.index'))
            ->post(route('knowledge.store'), [
                'title' => 'Drucker meldet Papierstau',
                'problem' => 'Drucker Modell X zeigt Papierstau, obwohl kein Papier klemmt.',
                'solution' => "1. Einzugsrolle reinigen\n2. Firmware aktualisieren",
                'category' => 'Drucker',
                'tags' => 'firmware, modell-x',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', [
            'title' => 'Drucker meldet Papierstau',
            'category' => 'Drucker',
            'status' => ArticleStatus::Draft->value,
            'created_by_user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        app()->instance('currentOrganization', $user->organization);
        $article = KnowledgeArticle::query()->firstOrFail();
        $this->assertNotSame('', $article->slug);
        $this->assertNull($article->published_at);
        $this->assertEqualsCanonicalizing(
            ['firmware', 'modell-x'],
            $article->tags->pluck('name')->all(),
        );
    }

    public function test_user_can_attach_files_when_creating_article(): void {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->from(route('knowledge.index'))
            ->post(route('knowledge.store'), [
                'title' => 'Artikel mit Anhang',
                'problem' => 'Problem mit Screenshot zur Veranschaulichung.',
                'solution' => 'Lösung samt Anleitung als PDF.',
                'attachments' => [
                    \Illuminate\Http\UploadedFile::fake()->image('screenshot.png'),
                    \Illuminate\Http\UploadedFile::fake()->create('anleitung.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $article = KnowledgeArticle::query()->where('title', 'Artikel mit Anhang')->firstOrFail();
        $this->assertSame(2, $article->attachments()->count());
        $this->assertEqualsCanonicalizing(
            ['screenshot.png', 'anleitung.pdf'],
            $article->attachments->pluck('original_name')->all(),
        );
    }

    public function test_callcenter_without_permission_cannot_access_knowledge(): void {
        $user = User::factory()->callcenter()->create();

        $this->actingAs($user)->get(route('knowledge.index'))->assertForbidden();

        $this->actingAs($user)
            ->post(route('knowledge.store'), [
                'title' => 'Verboten',
                'problem' => 'x',
                'solution' => 'y',
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void {
        $this->get(route('knowledge.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_update_own_draft_but_not_foreign_draft(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $article = $this->makeArticleFor($author);

        $this->actingAs($author)
            ->put(route('knowledge.update', $article), [
                'title' => 'Aktualisierter Titel',
                'problem' => 'Neues Fehlerbild',
                'solution' => 'Neue Lösung',
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame('Aktualisierter Titel', $article->title);

        $this->actingAs($other)
            ->put(route('knowledge.update', $article), [
                'title' => 'Übernahme',
                'problem' => 'x',
                'solution' => 'y',
            ])
            ->assertForbidden();
    }

    public function test_published_article_is_editable_by_teamleitung_but_not_by_author(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $article = $this->makeArticleFor($author, published: true);

        $this->actingAs($author)
            ->put(route('knowledge.update', $article), [
                'title' => 'Eigenmächtige Änderung',
                'problem' => 'x',
                'solution' => 'y',
            ])
            ->assertForbidden();

        $this->actingAs($lead)
            ->put(route('knowledge.update', $article), [
                'title' => 'Redaktionell korrigiert',
                'problem' => 'Korrigiertes Fehlerbild',
                'solution' => 'Korrigierte Lösung',
            ])
            ->assertRedirect();

        $this->assertSame('Redaktionell korrigiert', $article->refresh()->title);
    }

    public function test_publish_requires_permission_and_sets_published_at(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $article = $this->makeArticleFor($author);

        // user-Rolle hat kein knowledge.publish.
        $this->actingAs($author)
            ->post(route('knowledge.publish', $article))
            ->assertForbidden();

        $this->actingAs($lead)
            ->post(route('knowledge.publish', $article))
            ->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_archive_requires_publish_permission(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $article = $this->makeArticleFor($author, published: true);

        $this->actingAs($author)
            ->post(route('knowledge.archive', $article))
            ->assertForbidden();

        $this->actingAs($lead)
            ->post(route('knowledge.archive', $article))
            ->assertRedirect();

        $this->assertSame(ArticleStatus::Archived, $article->refresh()->status);
    }

    public function test_only_admin_can_delete_article(): void {
        $author = User::factory()->user()->create();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        $admin = User::factory()->admin()->create(['organization_id' => $author->organization_id]);
        $article = $this->makeArticleFor($author);

        $this->actingAs($lead)
            ->delete(route('knowledge.destroy', $article))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('knowledge.destroy', $article))
            ->assertRedirect(route('knowledge.index'));

        $this->assertSoftDeleted('knowledge_articles', ['id' => $article->id]);
    }

    public function test_feedback_counts_once_per_user_and_switching_updates_counts(): void {
        $author = User::factory()->user()->create();
        $voterA = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $voterB = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $article = $this->makeArticleFor($author, published: true);

        // Doppelte gleiche Wertung zählt nur einmal.
        $this->actingAs($voterA)->post(route('knowledge.feedback', $article), ['value' => 'helpful'])->assertRedirect();
        $this->actingAs($voterA)->post(route('knowledge.feedback', $article), ['value' => 'helpful'])->assertRedirect();

        $article->refresh();
        $this->assertSame(1, $article->helpful_count);
        $this->assertSame(0, $article->not_helpful_count);
        $this->assertSame(1, $article->feedback()->count());

        // Wechsel der Stimme verschiebt den Zähler, erzeugt keine zweite Zeile.
        $this->actingAs($voterA)->post(route('knowledge.feedback', $article), ['value' => 'notHelpful'])->assertRedirect();
        $article->refresh();
        $this->assertSame(0, $article->helpful_count);
        $this->assertSame(1, $article->not_helpful_count);
        $this->assertSame(1, $article->feedback()->count());

        // Zweiter User zählt zusätzlich.
        $this->actingAs($voterB)->post(route('knowledge.feedback', $article), ['value' => 'helpful'])->assertRedirect();
        $article->refresh();
        $this->assertSame(1, $article->helpful_count);
        $this->assertSame(1, $article->not_helpful_count);
        $this->assertSame(2, $article->feedback()->count());
    }

    public function test_index_search_and_category_filter(): void {
        $user = User::factory()->user()->create();
        app()->instance('currentOrganization', $user->organization);

        KnowledgeArticle::factory()->published()->create([
            'title' => 'Papierstau am Drucker',
            'problem' => 'Einzugsrolle verschlissen',
            'category' => 'Drucker',
            'created_by_user_id' => $user->id,
        ]);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'VPN-Verbindung bricht ab',
            'problem' => 'MTU-Problem im Heimnetz',
            'category' => 'Netzwerk',
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('knowledge.index', ['q' => 'Einzugsrolle']))
            ->assertOk()
            ->assertSee('Papierstau am Drucker')
            ->assertDontSee('VPN-Verbindung bricht ab');

        $this->actingAs($user)
            ->get(route('knowledge.index', ['category' => 'Netzwerk']))
            ->assertOk()
            ->assertSee('VPN-Verbindung bricht ab')
            ->assertDontSee('Papierstau am Drucker');
    }

    public function test_index_hides_foreign_drafts_from_plain_users_and_status_filter_works_for_teamleitung(): void {
        $author = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $author->organization_id]);
        $lead = User::factory()->teamleitung()->create(['organization_id' => $author->organization_id]);
        app()->instance('currentOrganization', $author->organization);

        KnowledgeArticle::factory()->create([
            'title' => 'Geheimer Entwurf',
            'created_by_user_id' => $author->id,
        ]);
        KnowledgeArticle::factory()->published()->create([
            'title' => 'Öffentliches Wissen',
            'created_by_user_id' => $author->id,
        ]);

        // Fremder Entwurf ist für normale User in der Liste unsichtbar …
        $this->actingAs($other)
            ->get(route('knowledge.index'))
            ->assertOk()
            ->assertSee('Öffentliches Wissen')
            ->assertDontSee('Geheimer Entwurf');

        // … der Erfasser sieht seinen eigenen Entwurf.
        $this->actingAs($author)
            ->get(route('knowledge.index'))
            ->assertOk()
            ->assertSee('Geheimer Entwurf');

        // Teamleitung filtert gezielt nach Entwürfen.
        $this->actingAs($lead)
            ->get(route('knowledge.index', ['status' => ArticleStatus::Draft->value]))
            ->assertOk()
            ->assertSee('Geheimer Entwurf')
            ->assertDontSee('Öffentliches Wissen');
    }

    public function test_article_is_not_accessible_cross_organization(): void {
        $author = User::factory()->user()->create();
        $stranger = User::factory()->user()->create(); // eigene Organisation
        $article = $this->makeArticleFor($author, published: true);

        $this->actingAs($stranger)
            ->get(route('knowledge.show', $article))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('knowledge.feedback', $article), ['value' => 'helpful'])
            ->assertNotFound();
    }

    /** Artikel in der Organisation des Users (default: Entwurf). */
    private function makeArticleFor(User $creator, bool $published = false): KnowledgeArticle {
        $factory = KnowledgeArticle::factory();
        if ($published) {
            $factory = $factory->published();
        }

        return $factory->create([
            'organization_id' => $creator->organization_id,
            'created_by_user_id' => $creator->id,
        ]);
    }
}
