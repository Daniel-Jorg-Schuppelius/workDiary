<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BomResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, ArticleVariant, ArticleVariantBomOverride, ProcedureMaterialRequirement, ProcedureTemplateVersion};
use App\Services\Manufacturing\ManufacturingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Varianten-Stücklisten-Overrides (Feature 047, MVP-061): die aufgelöste,
 * eingefrorene Stückliste eines Auftrags berücksichtigt Menge-überschreiben,
 * deaktivieren und hinzufügen der konkreten Variante; andere Varianten bleiben
 * unberührt.
 */
final class BomResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ManufacturingOrderService $service;
    private ProcedureTemplateVersion $version;
    private Article $materialA;
    private Article $materialB;
    private Article $materialC;
    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->service = app(ManufacturingOrderService::class);
        $this->version = ProcedureTemplateVersion::factory()->create();
        $this->materialA = $this->material();
        $this->materialB = $this->material();
        $this->materialC = $this->material();

        ProcedureMaterialRequirement::factory()->perUnit('2')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->materialA->id, 'position_code' => 'P1',
        ]);
        ProcedureMaterialRequirement::factory()->perUnit('3')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->materialB->id, 'position_code' => 'P2',
        ]);

        $this->product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $this->version->id,
        ]);
    }

    public function test_variant_overrides_change_resolved_bom(): void {
        $variant = $this->variant();
        ArticleVariantBomOverride::create(['article_variant_id' => $variant->id, 'position_code' => 'P1', 'action' => 'override_qty', 'quantity' => '5']);
        ArticleVariantBomOverride::create(['article_variant_id' => $variant->id, 'position_code' => 'P2', 'action' => 'disable']);
        ArticleVariantBomOverride::create(['article_variant_id' => $variant->id, 'position_code' => 'P3', 'action' => 'add', 'article_id' => $this->materialC->id, 'quantity_kind' => 'per_unit', 'quantity' => '1', 'unit' => 'Stk']);

        $order = $this->service->release($this->service->createDraft($this->organization, $this->product, $variant, '10', 'Stk'));

        $byArticle = $order->materials->keyBy('article_id');
        $this->assertSame('50.0000', $byArticle[$this->materialA->id]->target_qty, 'P1 Menge überschrieben (5×10)');
        $this->assertArrayNotHasKey($this->materialB->id, $byArticle->all(), 'P2 deaktiviert');
        $this->assertSame('10.0000', $byArticle[$this->materialC->id]->target_qty, 'P3 hinzugefügt (1×10)');
    }

    public function test_other_variant_uses_base_bom_unchanged(): void {
        $variant = $this->variant();

        $order = $this->service->release($this->service->createDraft($this->organization, $this->product, $variant, '10', 'Stk'));

        $byArticle = $order->materials->keyBy('article_id');
        $this->assertSame('20.0000', $byArticle[$this->materialA->id]->target_qty);
        $this->assertSame('30.0000', $byArticle[$this->materialB->id]->target_qty);
    }

    private function material(): Article {
        return Article::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function variant(): ArticleVariant {
        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'option_signature' => 'v-' . fake()->unique()->numberBetween(1, 99999),
        ]);
    }
}
