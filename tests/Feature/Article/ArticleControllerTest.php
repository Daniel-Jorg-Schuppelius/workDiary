<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Article;

use App\Enums\Article\ArticleStatus;
use App\Models\{Article, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Admin-UI des Artikelstamms (Feature 048, MVP-060): CRUD-Berechtigungen,
 * SKU-Vergabe, Stilllegen-statt-Löschen über HTTP, Optionen/Varianten-Flow.
 */
final class ArticleControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('articles.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('articles.index'))->assertOk();
    }

    public function test_store_creates_article_with_sku_and_redirects(): void {
        $response = $this->actingAs($this->admin)->post(route('articles.store'), [
            'name' => 'Gussmasse',
            'type' => 'raw',
            'base_unit' => 'kg',
            'status' => 'active',
            'currency' => 'EUR',
            'stockable' => '1',
        ]);

        $article = Article::query()->where('name', 'Gussmasse')->firstOrFail();
        $response->assertRedirect(route('articles.show', $article));
        $this->assertStringStartsWith('ART-', (string) $article->number);
        $this->assertSame($this->organization->id, $article->organization_id);
    }

    public function test_store_forbidden_without_manage_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('articles.store'), [
            'name' => 'X', 'type' => 'raw', 'base_unit' => 'kg', 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_retire_sets_status_and_blocks_delete(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->post(route('articles.retire', $article))
            ->assertRedirect(route('articles.show', $article));
        $this->assertSame(ArticleStatus::Retired, $article->fresh()->status);

        // Stillgelegt ⇒ Löschen wird blockiert, Datensatz bleibt.
        $this->actingAs($this->admin)->delete(route('articles.destroy', $article))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    public function test_referenceless_draft_can_be_deleted(): void {
        $draft = Article::factory()->draft()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->delete(route('articles.destroy', $draft))
            ->assertRedirect(route('articles.index'));
        $this->assertDatabaseMissing('articles', ['id' => $draft->id]);
    }

    public function test_option_and_variant_flow(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->post(route('articles.options.store', $article), [
            'code' => 'color', 'name' => 'Farbe',
        ])->assertRedirect();
        $option = $article->optionDefinitions()->firstOrFail();

        $this->actingAs($this->admin)->post(route('articles.options.values.store', [$article, $option]), [
            'code' => 'red', 'label' => 'Rot',
        ])->assertRedirect();
        $value = $option->values()->firstOrFail();

        $this->actingAs($this->admin)->post(route('articles.variants.store', $article), [
            'option_value_ids' => [$value->id],
        ])->assertRedirect();

        $variant = $article->variants()->firstOrFail();
        $this->assertSame('color=red', $variant->option_signature);
        $this->assertStringStartsWith('ART-', (string) $variant->sku);
    }
}
