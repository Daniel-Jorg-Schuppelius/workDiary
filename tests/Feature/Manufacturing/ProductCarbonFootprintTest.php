<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductCarbonFootprintTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Enums\Article\ArticleType;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Procedure\{ProcedureMaterialRequirement, ProcedureTemplateVersion};
use App\Services\Manufacturing\ProductCarbonFootprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-960: CO₂-Fußabdruck je Stück aus der aufgelösten Stückliste. */
final class ProductCarbonFootprintTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_footprint_sums_purchased_parts_and_process_emissions(): void {
        $this->setUpOrganization();
        $versionFinished = ProcedureTemplateVersion::factory()->create();
        $versionSemi = ProcedureTemplateVersion::factory()->create();
        $steel = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Stahlblech', 'type' => ArticleType::Raw->value, 'manufacturable' => false, 'pcf_factor_kg' => '2.5000']);
        $paint = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lack', 'type' => ArticleType::Raw->value, 'manufacturable' => false]);
        foreach ([$steel, $paint] as $raw) {
            ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $raw->id, 'is_default' => true, 'option_signature' => 'r' . $raw->id]);
        }
        $semi = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gehäuse', 'type' => ArticleType::SemiFinished->value, 'manufacturable' => true, 'default_procedure_template_version_id' => $versionSemi->id, 'pcf_process_kg' => '0.5000']);
        ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $semi->id, 'is_default' => true, 'option_signature' => 's']);
        $finished = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schaltschrank', 'type' => ArticleType::Finished->value, 'manufacturable' => true, 'default_procedure_template_version_id' => $versionFinished->id, 'pcf_process_kg' => '1.0000']);

        // Schrank: 2 Gehäuse + 0,2 Lack; Gehäuse: 3 Stahlblech.
        ProcedureMaterialRequirement::factory()->perUnit('2')->create(['procedure_template_version_id' => $versionFinished->id, 'article_id' => $semi->id]);
        ProcedureMaterialRequirement::factory()->perUnit('0.2')->create(['procedure_template_version_id' => $versionFinished->id, 'article_id' => $paint->id]);
        ProcedureMaterialRequirement::factory()->perUnit('3')->create(['procedure_template_version_id' => $versionSemi->id, 'article_id' => $steel->id]);

        $result = app(ProductCarbonFootprintService::class)->footprint($finished);

        // Material 6 × 2,5 = 15; Prozess 1 + 2 × 0,5 = 2; Lack ohne Faktor fehlt.
        $this->assertSame('15.0000', $result['material_kg']);
        $this->assertSame('2.0000', $result['process_kg']);
        $this->assertSame('17.0000', $result['total_kg']);
        $this->assertFalse($result['complete']);
        $this->assertSame(['Lack'], array_map(static fn (Article $a): string => $a->name, $result['missing']));

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('articles.footprint', $finished))->assertOk()->assertSeeText('Stahlblech')->assertSeeText('17 kg');
        $this->actingAs($admin)->put(route('articles.footprint.update', $paint), ['pcf_factor_kg' => '4', 'pcf_source' => 'Herstellerangabe'])->assertSessionHas('success');
        $this->assertTrue(app(ProductCarbonFootprintService::class)->footprint($finished->fresh())['complete']);
    }
}
