<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMasterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Article;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Models\{Article, ArticleOptionDefinition, ArticleOptionValue, ArticleUnit, ExternalArticleMapping, Organization};
use App\Services\Article\{ArticleService, UnitConverter, VariantResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kanonischer Artikelstamm (Feature 048, MVP-060): SKU-Hoheit, eindeutige
 * Optionskombination je Artikel, Stilllegen statt Löschen, reproduzierbare
 * Einheiten-Umrechnung, Preisvererbung und Mandantengrenze.
 */
final class ArticleMasterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_create_article_assigns_local_sku_and_is_unique(): void {
        $service = app(ArticleService::class);

        $a = $service->createArticle($this->organization, ['name' => 'Kabel', 'type' => ArticleType::Raw->value, 'base_unit' => 'm']);
        $b = $service->createArticle($this->organization, ['name' => 'Schraube']);

        $this->assertNotNull($a->number);
        $this->assertStringStartsWith('ART-', (string) $a->number);
        $this->assertNotSame($a->number, $b->number, 'SKU je Organisation eindeutig');
    }

    public function test_variant_combination_is_unique_and_order_independent(): void {
        [$article, $values] = $this->articleWithOptions();
        $resolver = app(VariantResolver::class);

        $variant = $resolver->createVariant($article, [$values['red']->id, $values['m']->id]);
        $this->assertSame('color=red|size=m', $variant->option_signature);
        $this->assertSame($this->organization->id, $variant->organization_id);

        // Gleiche Kombination in anderer Reihenfolge → dieselbe Signatur → Konflikt.
        $this->expectException(RuntimeException::class);
        $resolver->createVariant($article, [$values['m']->id, $values['red']->id]);
    }

    public function test_variant_rejects_two_values_of_same_option(): void {
        [$article, $values] = $this->articleWithOptions();

        $this->expectException(RuntimeException::class);
        app(VariantResolver::class)->createVariant($article, [$values['red']->id, $values['blue']->id]);
    }

    public function test_variant_rejects_option_value_of_foreign_article(): void {
        [$article] = $this->articleWithOptions();
        [, $other] = $this->articleWithOptions();

        $this->expectException(RuntimeException::class);
        app(VariantResolver::class)->createVariant($article, [$other['red']->id]);
    }

    public function test_assign_variant_sku_is_idempotent(): void {
        [$article, $values] = $this->articleWithOptions();
        $service = app(ArticleService::class);
        $variant = app(VariantResolver::class)->createVariant($article, [$values['red']->id]);

        $service->assignVariantSku($variant);
        $first = $variant->sku;
        $this->assertNotNull($first);

        $service->assignVariantSku($variant->fresh());
        $this->assertSame($first, $variant->fresh()->sku, 'erneutes Zuweisen ändert die SKU nicht');
    }

    public function test_retire_keeps_record_and_blocks_delete(): void {
        $service = app(ArticleService::class);
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        $service->retire($article);
        $this->assertSame(ArticleStatus::Retired, $article->fresh()->status);
        $this->assertFalse($service->canDelete($article->fresh()), 'stillgelegt ⇒ nicht löschbar');

        $draft = Article::factory()->draft()->create(['organization_id' => $this->organization->id]);
        $this->assertTrue($service->canDelete($draft), 'referenzloser Entwurf ⇒ löschbar');

        ExternalArticleMapping::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'external_id' => 'lx-1',
            'article_id' => $draft->id,
        ]);
        $this->assertFalse($service->canDelete($draft->fresh()), 'Entwurf mit externer Zuordnung ⇒ nicht löschbar');
    }

    public function test_unit_converter_uses_factor_and_blocks_unmaintained_dimension(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'm']);
        ArticleUnit::factory()->create([
            'article_id' => $article->id,
            'code' => 'Rolle',
            'factor_to_base' => '100',
        ]);
        $converter = app(UnitConverter::class);

        $this->assertSame('200.0000', $converter->toBase($article, '2', 'Rolle'));
        $this->assertSame('5.0000', $converter->toBase($article, '5', 'm')); // Basiseinheit = Faktor 1
        $this->assertSame('2.0000', $converter->fromBase($article, '200', 'Rolle'));

        // Liter↔kg ohne gepflegten Faktor ⇒ keine Einheit ⇒ Fehler.
        $this->expectException(RuntimeException::class);
        $converter->toBase($article, '1', 'kg');
    }

    public function test_effective_sale_price_inherits_then_overrides(): void {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_sale_price' => '10.0000',
        ]);
        [, $values] = [$article, $this->optionsFor($article)];
        $resolver = app(VariantResolver::class);

        $inherits = $resolver->createVariant($article, [$values['red']->id]);
        $this->assertSame(10.0, (float) $inherits->effectiveSalePrice());

        $overrides = $resolver->createVariant($article, [$values['blue']->id], ['sale_price' => '12.5000']);
        $this->assertSame(12.5, (float) $overrides->effectiveSalePrice());
    }

    public function test_articles_are_isolated_per_organization(): void {
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'OrgA-Artikel']);

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, Article::query()->count(), 'Fremd-Org sieht keine Artikel');

        app()->instance('currentOrganization', $this->organization);
        $this->assertSame(1, Article::query()->count());
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    /**
     * Artikel mit Optionen color(red/blue) + size(m).
     *
     * @return array{0: Article, 1: array<string, ArticleOptionValue>}
     */
    private function articleWithOptions(): array {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return [$article, $this->optionsFor($article)];
    }

    /** @return array<string, ArticleOptionValue> */
    private function optionsFor(Article $article): array {
        $color = ArticleOptionDefinition::factory()->create(['article_id' => $article->id, 'code' => 'color']);
        $size = ArticleOptionDefinition::factory()->create(['article_id' => $article->id, 'code' => 'size']);

        return [
            'red' => ArticleOptionValue::factory()->create(['article_option_definition_id' => $color->id, 'code' => 'red']),
            'blue' => ArticleOptionValue::factory()->create(['article_option_definition_id' => $color->id, 'code' => 'blue']),
            'm' => ArticleOptionValue::factory()->create(['article_option_definition_id' => $size->id, 'code' => 'm']),
        ];
    }
}
