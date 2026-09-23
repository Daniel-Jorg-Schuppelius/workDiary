<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentCollectionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission;
use App\Models\Communication\CommunicationNote;
use App\Models\Ideas\IdeaMap;
use App\Models\Knowledge\{ContentCollection, ContentCollectionItem, KnowledgeArticle};
use App\Models\Platform\{Organization, User};
use App\Services\Collections\ContentCollectionService;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Learning\LearningCourseService;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Sammlungen (MVP-809, Feature 155): Ordnung über mehrere Inhaltstypen, ohne
 * dass eine Sammlung Zugriff verleiht.
 */
final class ContentCollectionsTest extends TestCase {
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

    public function test_note_idea_map_article_and_course_share_one_collection(): void {
        $collection = $this->collection($this->admin, 'Heizungsprojekt Schule');
        $note = $this->note($this->admin, 'Telefonat Hausmeister');
        $map = IdeaMap::factory()->create(['organization_id' => $this->organization->id, 'owner_user_id' => $this->admin->id, 'created_by' => $this->admin->id, 'title' => 'Ideen Wärmepumpe']);
        $article = KnowledgeArticle::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Entlüften der Heizkreise', 'status' => ArticleStatus::Published->value]);
        $course = app(LearningCourseService::class)->createCourse($this->organization, $this->admin, ['title' => 'Kältemittel-Sachkunde']);

        foreach ([['note', $note], ['idea_map', $map], ['knowledge_article', $article], ['learning_course', $course]] as [$type, $item]) {
            $this->actingAs($this->admin)
                ->post(route('collections.items.store'), ['collection' => $collection->sqid, 'type' => $type, 'item' => $item->sqid])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)->get(route('collections.index', ['collection' => $collection->sqid]))
            ->assertOk()
            ->assertSee('Telefonat Hausmeister')
            ->assertSee('Ideen Wärmepumpe')
            ->assertSee('Entlüften der Heizkreise')
            ->assertSee('Kältemittel-Sachkunde');
    }

    public function test_an_item_lives_in_two_collections_without_a_copy(): void {
        $first = $this->collection($this->admin, 'Kunde Müller');
        $second = $this->collection($this->admin, 'Gewährleistung');
        $note = $this->note($this->admin, 'Mängelrüge Dach');
        $service = app(ContentCollectionService::class);

        $service->addItem($first, $this->admin, $note);
        $service->addItem($second, $this->admin, $note);
        $service->addItem($second, $this->admin, $note);

        $this->assertSame(2, ContentCollectionItem::query()->count());
        $this->assertSame(1, CommunicationNote::query()->where('subject', 'Mängelrüge Dach')->count());
        $this->assertCount(2, $service->collectionsContaining($note, $this->admin));
    }

    public function test_a_confidential_note_stays_hidden_inside_a_visible_collection(): void {
        $author = $this->member([Permission::CommunicationConfidentialManage]);
        $collection = $this->collection($author, 'Personal');
        $confidential = $this->note($author, 'Personalgespräch Abmahnung', confidential: true);
        $open = $this->note($author, 'Schulungsplanung');
        $service = app(ContentCollectionService::class);
        $service->addItem($collection, $author, $confidential);
        $service->addItem($collection, $author, $open);

        $colleague = $this->member();
        $this->actingAs($colleague)->get(route('collections.index', ['collection' => $collection->sqid]))
            ->assertOk()
            ->assertSee('Schulungsplanung')
            ->assertDontSee('Personalgespräch Abmahnung');

        // Einsortieren kann sie die fremde vertrauliche Notiz ebenso wenig.
        $other = $this->collection($colleague, 'Meine Sammlung');
        $this->actingAs($colleague)
            ->post(route('collections.items.store'), ['collection' => $other->sqid, 'type' => 'note', 'item' => $confidential->sqid])
            ->assertNotFound();
    }

    public function test_courses_disappear_without_the_lms_module_while_notes_stay(): void {
        $collection = $this->collection($this->admin, 'Einarbeitung');
        $service = app(ContentCollectionService::class);
        $service->addItem($collection, $this->admin, $this->note($this->admin, 'Checkliste erster Tag'));
        $service->addItem($collection, $this->admin, app(LearningCourseService::class)->createCourse($this->organization, $this->admin, ['title' => 'Arbeitsschutz Grundlagen']));

        config(['license.feature_overrides' => ['module.lms' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $titles = array_column($service->visibleItems($collection, $this->admin), 'title');
        $this->assertSame(['Checkliste erster Tag'], $titles);

        // Ohne Lernrechte sieht auch eine Person mit Modul den Kurs nicht.
        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();
        $this->assertSame(['Checkliste erster Tag'], array_column($service->visibleItems($collection, $this->member()), 'title'));
    }

    public function test_tree_depth_is_limited_and_cycles_are_rejected(): void {
        $service = app(ContentCollectionService::class);
        $parent = null;
        $chain = [];
        for ($level = 1; $level <= ContentCollection::MAX_DEPTH; $level++) {
            $parent = $service->create($this->organization, $this->admin, ['title' => 'Ebene ' . $level, 'parent_id' => $parent?->id]);
            $chain[] = $parent;
        }

        try {
            $service->create($this->organization, $this->admin, ['title' => 'Zu tief', 'parent_id' => $parent->id]);
            $this->fail('Sechste Ebene angelegt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }

        try {
            $service->update($chain[0], $this->admin, ['title' => 'Ebene 1', 'parent_id' => $chain[2]->id]);
            $this->fail('Zyklus angelegt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }

        // Ein Teilbaum mit vier Ebenen (Ebene 2–5) passt nicht unter eine Sammlung auf Ebene 2.
        $side = $service->create($this->organization, $this->admin, ['title' => 'Seitenast']);
        $sideChild = $service->create($this->organization, $this->admin, ['title' => 'Seitenast Kind', 'parent_id' => $side->id]);
        $this->expectException(ValidationException::class);
        $service->update($chain[1], $this->admin, ['title' => 'Ebene 2', 'parent_id' => $sideChild->id]);
    }

    public function test_a_private_collection_stays_closed_even_for_admins(): void {
        $owner = $this->member();
        $private = app(ContentCollectionService::class)->create($this->organization, $owner, ['title' => 'Meine Merkliste', 'visibility' => ContentCollection::VISIBILITY_PRIVATE]);

        $this->actingAs($owner)->get(route('collections.index'))->assertOk()->assertSee('Meine Merkliste');

        $this->actingAs($this->admin)->get(route('collections.index'))->assertOk()->assertDontSee('Meine Merkliste');
        $this->actingAs($this->admin)->get(route('collections.index', ['collection' => $private->sqid]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('collections.edit', $private))->assertNotFound();
    }

    public function test_rights_and_tenant_boundary(): void {
        $reader = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($reader, [Permission::CollectionViewAny, Permission::CommunicationViewAny, Permission::CommunicationView]);
        $collection = $this->collection($this->admin, 'Team');

        $this->actingAs($reader)->get(route('collections.index'))->assertOk();
        $this->actingAs($reader)->post(route('collections.store'), ['title' => 'Neu', 'visibility' => 'organization'])->assertForbidden();

        $outsider = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($outsider)->get(route('collections.index'))->assertForbidden();

        // Inhalt einer fremden Organisation lässt sich nicht einsortieren.
        $foreignOrg = Organization::factory()->create();
        $foreignArticle = KnowledgeArticle::factory()->create(['organization_id' => $foreignOrg->id, 'status' => ArticleStatus::Published->value]);
        $this->actingAs($this->admin)
            ->post(route('collections.items.store'), ['collection' => $collection->sqid, 'type' => 'knowledge_article', 'item' => $foreignArticle->sqid])
            ->assertNotFound();
        $this->assertSame(0, ContentCollectionItem::query()->count());
    }

    public function test_archived_collections_leave_the_tree_and_come_back(): void {
        $collection = $this->collection($this->admin, 'Altprojekt 2024');

        $this->actingAs($this->admin)->post(route('collections.archive', $collection))->assertRedirect();
        $this->actingAs($this->admin)->get(route('collections.index'))->assertOk()->assertDontSee('Altprojekt 2024');
        $this->actingAs($this->admin)->get(route('collections.index', ['archived' => 1]))->assertOk()->assertSee('Altprojekt 2024');

        $this->actingAs($this->admin)->post(route('collections.restore', $collection))->assertRedirect();
        $this->assertNull($collection->fresh()?->archived_at);
    }

    public function test_detail_pages_offer_adding_to_a_collection(): void {
        $article = KnowledgeArticle::factory()->create(['organization_id' => $this->organization->id, 'status' => ArticleStatus::Published->value]);

        $this->actingAs($this->admin)->get(route('knowledge.show', $article))
            ->assertOk()
            ->assertSee(e(route('collections.add', ['type' => 'knowledge_article', 'item' => $article->sqid])), false);

        $this->collection($this->admin, 'Wissen Heizung');
        $this->actingAs($this->admin)->get(route('collections.add', ['type' => 'knowledge_article', 'item' => $article->sqid]))
            ->assertOk()
            ->assertSee('Wissen Heizung');
    }

    private function collection(User $actor, string $title): ContentCollection {
        return app(ContentCollectionService::class)->create($this->organization, $actor, ['title' => $title]);
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
