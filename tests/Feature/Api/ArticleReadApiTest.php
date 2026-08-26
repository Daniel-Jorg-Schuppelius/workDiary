<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Article\ArticleStatus;
use App\Models\{Article, ArticleVariant, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Read-only-REST Artikel + Varianten. */
final class ArticleReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_missing_ability_is_forbidden(): void {
        Sanctum::actingAs($this->admin, ['diary:read']);

        $this->getJson(route('api.articles.index'))->assertForbidden();
    }

    public function test_index_paginates_and_filters(): void {
        Article::factory()->count(3)->create(['organization_id' => $this->organization->id, 'name' => 'Schraube M8']);
        Article::factory()->draft()->create(['organization_id' => $this->organization->id, 'name' => 'Entwurf X']);
        Sanctum::actingAs($this->admin, ['articles:read']);

        $page = $this->getJson(route('api.articles.index', ['per_page' => 2]))->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(4, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.last_page'));

        $filtered = $this->getJson(route('api.articles.index', ['status' => ArticleStatus::Draft->value]))->assertOk();
        $this->assertCount(1, $filtered->json('data'));
        $this->assertSame('Entwurf X', $filtered->json('data.0.name'));

        $search = $this->getJson(route('api.articles.index', ['search' => 'Schraube']))->assertOk();
        $this->assertCount(3, $search->json('data'));
    }

    public function test_show_returns_sqid_and_variants(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'SKU-1', 'is_default' => true]);
        Sanctum::actingAs($this->admin, ['articles:read']);

        $this->getJson(route('api.articles.show', $article))
            ->assertOk()
            ->assertJsonPath('data.id', $article->sqid)
            ->assertJsonPath('data.variants.0.id', $variant->sqid)
            ->assertJsonPath('data.variants.0.sku', 'SKU-1')
            ->assertJsonMissingPath('data.variants.0.organization_id');

        $this->getJson(route('api.articles.variants', $article))
            ->assertOk()
            ->assertJsonPath('data.0.article_id', $article->sqid);
    }

    public function test_foreign_organization_article_is_not_found(): void {
        $other = Organization::factory()->create();
        $foreign = Article::factory()->create(['organization_id' => $other->id]);
        Sanctum::actingAs($this->admin, ['articles:read']);

        $this->getJson(route('api.articles.show', $foreign))->assertNotFound();
        $this->assertCount(0, $this->getJson(route('api.articles.index'))->json('data'));
    }

    public function test_free_plan_is_gated_like_web(): void {
        $this->organization->update(['plan' => Organization::PLAN_FREE]);
        Sanctum::actingAs($this->admin->fresh() ?? $this->admin, ['articles:read']);

        $this->getJson(route('api.articles.index'))->assertStatus(423);
    }
}
