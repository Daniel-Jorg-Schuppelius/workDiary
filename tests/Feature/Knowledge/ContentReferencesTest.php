<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentReferencesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission;
use App\Models\Audit\AuditLog;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Ideas\IdeaMap;
use App\Models\Knowledge\{ContentReference, KnowledgeArticle};
use App\Models\Platform\{Organization, User};
use App\Services\Collections\ContentReferenceService;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Ideas\{IdeaMapService, IdeaNodeService, NodeConversionService};
use App\Services\Knowledge\KnowledgeArticleService;
use App\Services\Stammdaten\CustomerMergeService;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Verweise und Rückverweise (MVP-811, Feature 155): ein Verweismodell für
 * Wissensverknüpfungen, Ideenknoten-Referenzen und von Hand gesetzte Verweise.
 */
final class ContentReferencesTest extends TestCase {
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

    public function test_a_reference_set_on_a_note_appears_as_backlink_on_the_article(): void {
        $note = $this->note($this->admin, 'Telefonat Heizungsbauer');
        $article = $this->article('Heizkreise entlüften');

        $this->actingAs($this->admin)->get(route('references.create', ['type' => 'note', 'item' => $note->sqid]))
            ->assertOk()
            ->assertSee('Heizkreise entlüften');

        $this->actingAs($this->admin)->post(route('references.store'), [
            'type' => 'note',
            'item' => $note->sqid,
            'target' => 'knowledge_article:' . $article->sqid,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('content_references', [
            'source_type' => $note->getMorphClass(),
            'source_id' => $note->id,
            'target_type' => $article->getMorphClass(),
            'target_id' => $article->id,
            'kind' => ContentReference::KIND_MENTIONED,
        ]);
        $this->assertTrue(AuditLog::query()->where('event', 'content_reference.added')->exists());

        $this->actingAs($this->admin)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertSee(__('collections.references.backlinks'))
            ->assertSee('Telefonat Heizungsbauer');

        // Einmal gesetzt, bietet der Dialog das Ziel nicht erneut an.
        $this->actingAs($this->admin)->get(route('references.create', ['type' => 'note', 'item' => $note->sqid]))
            ->assertOk()
            ->assertDontSee('Heizkreise entlüften');
    }

    public function test_backlinks_leave_out_sources_the_viewer_may_not_open(): void {
        $author = $this->member([Permission::CommunicationConfidentialManage, Permission::KnowledgeViewAny, Permission::KnowledgeView]);
        $article = $this->article('Abmahnung formulieren');
        $confidential = $this->note($author, 'Personalgespräch Meier', confidential: true);
        app(ContentReferenceService::class)->add($confidential, $article, $author);

        $this->actingAs($author)->get(route('knowledge.show', $article))->assertOk()->assertSee('Personalgespräch Meier');

        $colleague = $this->member([Permission::KnowledgeViewAny, Permission::KnowledgeView]);
        $this->actingAs($colleague)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertDontSee('Personalgespräch Meier');
    }

    public function test_idea_node_conversion_shows_up_with_its_map_and_only_for_map_readers(): void {
        $map = app(IdeaMapService::class)->create($this->organization, $this->admin, 'Ideen Lager');
        $node = app(IdeaNodeService::class)->create($map, $map->rootNode()->firstOrFail(), 'Regale beschriften', $this->admin);
        $reference = app(NodeConversionService::class)->convertToKnowledgeArticle($node, $this->admin);
        $article = KnowledgeArticle::query()->findOrFail($reference->target_id);

        $this->actingAs($this->admin)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertSee('Regale beschriften')
            ->assertSee('Ideen Lager')
            ->assertSee(__('collections.references.kind.converted'));

        // Private Karte eines anderen: der Knoten bleibt verborgen (der Artikel trägt den Knotentitel, daher die Karte prüfen).
        $reader = $this->member([Permission::KnowledgeViewAny, Permission::KnowledgeView, Permission::IdeasViewAny]);
        $this->actingAs($reader)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertDontSee('Ideen Lager')
            ->assertDontSee(__('collections.references.backlinks'));
    }

    public function test_references_need_both_sides_visible_and_never_point_to_themselves(): void {
        $note = $this->note($this->admin, 'Rückruf Kunde');

        $this->actingAs($this->admin)->post(route('references.store'), [
            'type' => 'note',
            'item' => $note->sqid,
            'target' => 'note:' . $note->sqid,
        ])->assertSessionHasErrors('target');

        $foreign = KnowledgeArticle::factory()->create(['organization_id' => Organization::factory()->create()->id, 'status' => ArticleStatus::Published->value]);
        $this->actAsTeam($this->organization);
        $this->actingAs($this->admin)->post(route('references.store'), [
            'type' => 'note',
            'item' => $note->sqid,
            'target' => 'knowledge_article:' . $foreign->sqid,
        ])->assertNotFound();

        $this->assertSame(0, ContentReference::query()->count());
    }

    public function test_only_hand_set_references_can_be_removed_here(): void {
        $note = $this->note($this->admin, 'Messeplanung');
        $article = $this->article('Standaufbau');
        $mentioned = app(ContentReferenceService::class)->add($note, $article, $this->admin);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $linked = app(KnowledgeArticleService::class)->linkTo($article, $customer, $this->admin);

        $this->actingAs($this->admin)->delete(route('references.destroy', $linked))->assertSessionHasErrors('reference');
        $this->assertNotNull($linked->fresh());

        $this->actingAs($this->admin)->delete(route('references.destroy', $mentioned))->assertSessionHasNoErrors();
        $this->assertNull($mentioned->fresh());
        $this->assertTrue(AuditLog::query()->where('event', 'content_reference.removed')->exists());
    }

    public function test_setting_references_needs_the_collection_right_while_backlinks_stay_readable(): void {
        $article = $this->article('Kältemittel wechseln');
        app(ContentReferenceService::class)->add($this->note($this->admin, 'Notiz Kältemittel'), $article, $this->admin);

        $reader = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($reader, [Permission::KnowledgeViewAny, Permission::KnowledgeView, Permission::CommunicationViewAny, Permission::CommunicationView]);

        $this->actingAs($reader)->get(route('references.create', ['type' => 'knowledge_article', 'item' => $article->sqid]))->assertForbidden();
        $this->actingAs($reader)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertSee('Notiz Kältemittel')
            ->assertDontSee(route('references.create', ['type' => 'knowledge_article', 'item' => $article->sqid]), false);
    }

    public function test_customer_merge_moves_references_without_duplicates(): void {
        $article = $this->article('Zugang Serverraum');
        $other = $this->article('Schlüsselliste');
        $source = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Müller GmbH']);
        $target = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Müller GmbH & Co. KG']);
        $knowledge = app(KnowledgeArticleService::class);
        $knowledge->linkTo($article, $source, $this->admin);
        $knowledge->linkTo($article, $target, $this->admin);
        $knowledge->linkTo($other, $source, $this->admin);

        app(CustomerMergeService::class)->merge($source, $target);

        $onTarget = ContentReference::query()->where('target_type', $target->getMorphClass())->where('target_id', $target->id)->pluck('source_id')->sort()->values()->all();
        $this->assertSame([$article->id, $other->id], $onTarget);
        $this->assertSame(0, ContentReference::query()->where('target_type', $source->getMorphClass())->where('target_id', $source->id)->count());
    }

    public function test_purging_an_idea_map_removes_the_references_of_its_nodes(): void {
        $map = app(IdeaMapService::class)->create($this->organization, $this->admin, 'Altkarte');
        $node = app(IdeaNodeService::class)->create($map, $map->rootNode()->firstOrFail(), 'Knoten', $this->admin);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        app(NodeConversionService::class)->linkTo($node, $customer, $this->admin);

        // Papierkorb zählt als vorhanden.
        $map->delete();
        $this->assertSame(0, ContentReference::pruneOrphans($this->organization->id));

        IdeaMap::withTrashed()->findOrFail($map->id)->forceDelete();
        $this->assertSame(1, ContentReference::pruneOrphans($this->organization->id));
        $this->assertSame(0, ContentReference::query()->count());
    }

    public function test_diary_page_leaves_knowledge_links_to_the_knowledge_card(): void {
        $entry = \App\Models\DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);
        $map = app(IdeaMapService::class)->create($this->organization, $this->admin, 'Karte Auftrag');
        $node = app(IdeaNodeService::class)->create($map, $map->rootNode()->firstOrFail(), 'Folgeauftrag prüfen', $this->admin);
        app(NodeConversionService::class)->linkTo($node, $entry, $this->admin);
        app(KnowledgeArticleService::class)->linkTo($this->article('Druckerwartung'), $entry, $this->admin);

        $rows = app(ContentReferenceService::class)->backlinks($entry, $this->admin, withoutKnowledgeLinks: true);
        $this->assertSame(['idea_map'], array_column($rows, 'type'));
        $this->assertSame('Folgeauftrag prüfen', $rows[0]['items'][0]['title']);
        $this->assertSame(Sqid::encode(IdeaMap::class, $map->id), basename(parse_url($rows[0]['items'][0]['url'], PHP_URL_PATH) ?: ''));
    }

    private function article(string $title): KnowledgeArticle {
        return KnowledgeArticle::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => $title,
            'status' => ArticleStatus::Published->value,
            // Ohne eigenen Autor legt die Factory eine fremde Organisation an und verstellt das Rechte-Team.
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    private function note(User $actor, string $subject, bool $confidential = false): CommunicationNote {
        return app(CommunicationNoteService::class)->create($this->organization, $actor, [
            'type' => CommunicationNoteType::General->value,
            'direction' => CommunicationDirection::Internal->value,
            'occurred_at' => now()->subHour()->toDateTimeString(),
            'subject' => $subject,
            'body' => 'Inhalt.',
            'confidential' => $confidential,
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
