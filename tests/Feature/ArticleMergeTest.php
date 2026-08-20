<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMergeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Article, ArticleMergeDismissal, ArticleVariant, User};
use App\Services\{ArticleDuplicateFinder, ArticleMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Artikel-Zusammenführung (Audit 2026-08, W2.9; Semantik:
 * `WorkDiary-Architecture/artikel-merge-semantik.md`).
 *
 * Kern der Prüfungen: Varianten wandern als Ganzes mit — der append-only
 * Lagerledger wird NIE umgeschrieben —, und der Merge verweigert sich, wo
 * die Zusammenführung nicht eindeutig entscheidbar wäre.
 */
class ArticleMergeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function article(array $attributes = []): Article {
        return Article::factory()->create(array_merge(['organization_id' => $this->organization->id], $attributes));
    }

    private function variant(Article $article, string $signature, string $sku): ArticleVariant {
        return ArticleVariant::query()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => $sku,
            'name' => 'Variante ' . $signature,
            'option_signature' => $signature,
        ]);
    }

    public function test_variants_move_as_a_whole_and_source_is_deleted(): void {
        $target = $this->article(['name' => 'Kabel NYM-J 3x1,5', 'number' => 'A-100']);
        $source = $this->article(['name' => 'Kabel NYM-J 3x1.5', 'number' => null, 'description' => 'Aus Katalog-Import']);

        $variant = $this->variant($source, 'laenge:50', 'SKU-SRC-50');

        app(ArticleMergeService::class)->merge($source, $target);

        $this->assertDatabaseMissing('articles', ['id' => $source->id]);
        // Variante hängt jetzt am Ziel — dieselbe Variante, derselbe Bestand.
        $this->assertSame((int) $target->id, (int) $variant->fresh()->article_id);
        $this->assertSame('SKU-SRC-50', $variant->fresh()->sku);

        // Leeres Zielfeld aus der Quelle gefüllt.
        $this->assertSame('Aus Katalog-Import', $target->fresh()->description);
    }

    public function test_merge_is_refused_when_both_have_the_same_option_signature(): void {
        $target = $this->article(['name' => 'Ziel']);
        $source = $this->article(['name' => 'Quelle']);
        $this->variant($target, 'laenge:50', 'SKU-T-50');
        $this->variant($source, 'laenge:50', 'SKU-S-50');

        $this->expectException(\InvalidArgumentException::class);
        app(ArticleMergeService::class)->merge($source, $target);
    }

    /** Artikel mit abweichender Basiseinheit tauchen erst gar nicht als Vorschlag auf. */
    public function test_finder_skips_pairs_the_merge_would_refuse(): void {
        $this->article(['name' => 'Kabel NYM 3x1,5', 'base_unit' => 'm']);
        $this->article(['name' => 'Kabel NYM 3x1,5', 'base_unit' => 'Stk']);

        $this->assertCount(0, app(ArticleDuplicateFinder::class)->candidates($this->organization));
    }

    public function test_merge_is_refused_for_different_base_unit_or_tax_class(): void {
        $target = $this->article(['base_unit' => 'Stk']);
        $source = $this->article(['base_unit' => 'm']);

        $this->expectException(\InvalidArgumentException::class);
        app(ArticleMergeService::class)->merge($source, $target);
    }

    public function test_merge_is_refused_across_organizations(): void {
        $own = $this->article();
        $foreign = Article::factory()->create(['organization_id' => \App\Models\Organization::factory()->create()->id]);

        $this->expectException(\InvalidArgumentException::class);
        app(ArticleMergeService::class)->merge($foreign, $own);
    }

    /**
     * Wichtig: `articles` ist org-weit unique ueber `number` UND `gtin` -
     * doppelte Kennungen kann es gar nicht geben. Der Finder arbeitet deshalb
     * ueber Namensaehnlichkeit (eigenes Dubletten-Profil), nicht ueber das
     * Import-Match-Profil.
     */
    public function test_finder_detects_duplicates_by_similar_name_and_respects_dismissals(): void {
        $a = $this->article(['name' => 'Schalter UP 250V', 'number' => 'A-777']);
        $b = $this->article(['name' => 'Schalter UP 250 V', 'number' => 'A-778']);

        $this->assertCount(1, app(ArticleDuplicateFinder::class)->candidates($this->organization));

        ArticleMergeDismissal::query()->create(array_merge(
            ArticleMergeDismissal::pairKey((int) $a->id, (int) $b->id),
            ['organization_id' => $this->organization->id, 'dismissed_by' => $this->admin->id],
        ));

        $this->assertCount(0, app(ArticleDuplicateFinder::class)->candidates($this->organization));
    }

    public function test_ui_lists_and_merges(): void {
        $target = $this->article(['name' => 'Dose tief', 'number' => 'A-500']);
        $source = $this->article(['name' => 'Dose tief', 'number' => 'A-501']);

        $this->actingAs($this->admin)
            ->get(route('articles.duplicates.index'))
            ->assertOk()
            ->assertSee('Dose tief');

        $this->actingAs($this->admin)
            ->post(route('articles.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertRedirect(route('articles.duplicates.index'));

        $this->assertDatabaseMissing('articles', ['id' => $source->id]);
    }

    public function test_refused_merge_surfaces_as_form_error_not_server_error(): void {
        $target = $this->article(['base_unit' => 'Stk']);
        $source = $this->article(['base_unit' => 'kg']);

        $this->actingAs($this->admin)
            ->post(route('articles.duplicates.merge'), ['source' => $source->sqid, 'target' => $target->sqid])
            ->assertSessionHasErrors('source');

        $this->assertDatabaseHas('articles', ['id' => $source->id]);
    }
}
